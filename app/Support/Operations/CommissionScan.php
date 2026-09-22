<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Actions\Alerts\RaiseAlert;
use App\Actions\Alerts\ResolveAlert;
use App\Enums\AgencyStatus;
use App\Enums\AlertKind;
use App\Enums\BookingStatus;
use App\Enums\ChannelOfOrigin;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Booking;
use App\Services\Config\CurrentConfig;
use App\Support\Alerts\AlertKeys;
use App\Support\Crm\ContactDerived;
use App\Support\Crm\TaskSweep;
use Illuminate\Database\Eloquent\Builder;

final class CommissionScan
{
    public function __construct(
        private readonly RaiseAlert $raise,
        private readonly ResolveAlert $resolve,
        private readonly CurrentConfig $config,
    ) {}

    public function run(): void
    {
        $keys = [
            ...$this->tradeWithoutAgency(),
            ...$this->advisorWithoutAgency(),
            ...$this->escapedCap(),
            ...$this->missingTerms(),
        ];

        $this->resolveCleared($keys);
    }

    /**
     * The trade channels this scan treats as needing an agency.
     * Not the wider trade-and-corporate group.
     *
     * @return list<ChannelOfOrigin>
     */
    public static function tradeChannels(): array
    {
        return [
            ChannelOfOrigin::TravelAdvisor,
            ChannelOfOrigin::LuxuryAgency,
            ChannelOfOrigin::HostAgency,
            ChannelOfOrigin::Consortia,
            ChannelOfOrigin::TourOperator,
            ChannelOfOrigin::LuxuryTourOperator,
            ChannelOfOrigin::Dmc,
            ChannelOfOrigin::IncomingOperator,
        ];
    }

    /**
     * @return list<string>
     */
    private function tradeWithoutAgency(): array
    {
        $keys = [];

        $this->soldWithoutAgency()
            ->whereIn('channel_of_origin', array_map(
                fn (ChannelOfOrigin $channel): string => $channel->value,
                self::tradeChannels(),
            ))
            ->orderBy('id')
            ->each(function (Booking $booking) use (&$keys): void {
                $keys[] = $this->raiseBooking(
                    $booking,
                    AlertKeys::leakTrade($booking->id),
                    'channel of origin is '.$booking->channel_of_origin->value.' and the booking has no agency',
                );
            });

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function advisorWithoutAgency(): array
    {
        $keys = [];

        $this->soldWithoutAgency()
            ->whereHas('bookingRequest', function (Builder $request): void {
                $request->where('travel_advisor', true);
            })
            ->orderBy('id')
            ->each(function (Booking $booking) use (&$keys): void {
                $keys[] = $this->raiseBooking(
                    $booking,
                    AlertKeys::leakAdvisor($booking->id),
                    'the booking request was marked travel advisor and the booking has no agency',
                );
            });

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function escapedCap(): array
    {
        $cap = $this->config->businessRules()->commission->capPct;
        $keys = [];

        Booking::query()
            ->with('agency')
            ->whereNotNull('agency_id')
            ->where('commission_approved', false)
            ->where('status', '!=', BookingStatus::OnHoldAgency)
            ->whereNotIn('status', [
                BookingStatus::Cancelled,
                BookingStatus::CancelledPostpaid,
                BookingStatus::Released,
            ])
            ->whereHas('agency', function (Builder $agency) use ($cap): void {
                $agency->where('status', AgencyStatus::Approved)
                    ->where('commission_pct', '>', $cap);
            })
            ->orderBy('id')
            ->each(function (Booking $booking) use (&$keys, $cap): void {
                $reference = TaskSweep::reference($booking);
                $name = $booking->agency->name ?? 'agency';
                $key = AlertKeys::leakCap($booking->id);

                $this->raise->handle(
                    AlertKind::CommissionLeakage,
                    $key,
                    'Commission leakage '.$reference,
                    $reference.' is not held and not approved, and '.$name.' commission is above the '.$cap.'% cap.',
                    bookingId: $booking->id,
                    agencyId: $booking->agency_id,
                );
                $keys[] = $key;
            });

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function missingTerms(): array
    {
        $keys = [];

        Agency::query()
            ->where('status', AgencyStatus::Approved)
            ->where(function (Builder $query): void {
                $query->whereNull('payment_terms')->orWhere('payment_terms', '');
            })
            ->orderBy('id')
            ->each(function (Agency $agency) use (&$keys): void {
                $key = AlertKeys::leakTerms($agency->id);

                $this->raise->handle(
                    AlertKind::CommissionLeakage,
                    $key,
                    'Commission leakage '.$agency->reference,
                    $agency->name.' is approved and has no payment terms.',
                    agencyId: $agency->id,
                );
                $keys[] = $key;
            });

        return $keys;
    }

    /**
     * @return Builder<Booking>
     */
    private function soldWithoutAgency(): Builder
    {
        return Booking::query()
            ->whereNull('agency_id')
            ->whereIn('status', ContactDerived::soldStatuses());
    }

    private function raiseBooking(Booking $booking, string $key, string $because): string
    {
        $reference = TaskSweep::reference($booking);

        $this->raise->handle(
            AlertKind::CommissionLeakage,
            $key,
            'Commission leakage '.$reference,
            $reference.': '.$because.'.',
            bookingId: $booking->id,
        );

        return $key;
    }

    /**
     * @param  list<string>  $keys
     */
    private function resolveCleared(array $keys): void
    {
        $query = Alert::query()
            ->where('kind', AlertKind::CommissionLeakage)
            ->unresolved()
            ->orderBy('id');

        if ($keys !== []) {
            $query->whereNotIn('base_key', $keys);
        }

        $query->each(function (Alert $alert): void {
            $this->resolve->handle($alert, 'the finding is gone');
        });
    }
}
