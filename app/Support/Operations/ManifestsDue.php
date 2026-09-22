<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Actions\Alerts\RaiseAlert;
use App\Actions\Alerts\ResolveAlert;
use App\Actions\Manifests\IssueManifest;
use App\Actions\Manifests\SendDataChaser;
use App\Enums\AlertKind;
use App\Enums\BookingStatus;
use App\Enums\ManifestKind;
use App\Models\Alert;
use App\Models\Booking;
use App\Models\Departure;
use App\Support\Alerts\AlertKeys;
use App\Support\BusinessTime;
use App\Support\Manifests\ManifestDue;
use App\Support\Manifests\ManifestPassenger;
use App\Support\Manifests\ManifestRoster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

final class ManifestsDue
{
    public function __construct(
        private readonly IssueManifest $issue,
        private readonly RaiseAlert $raise,
        private readonly ResolveAlert $resolve,
        private readonly SendDataChaser $chasers,
    ) {}

    public function run(): void
    {
        $today = BusinessTime::now()->toDateString();

        $this->resolveSailed($today);
        $this->beforeSailing($today);
    }

    private function beforeSailing(string $today): void
    {
        $this->departuresOnOrAfter($today)->each(function (Departure $departure) use ($today): void {
            $passengers = ManifestRoster::passengers($departure);

            if ($passengers->isEmpty()) {
                return;
            }

            $due = ManifestDue::forDeparture($departure, $passengers);
            $departureDate = $departure->date->toDateString();
            $before = $today < $departureDate;

            foreach (ManifestKind::cases() as $kind) {
                $kindDue = $kind === ManifestKind::Dpng ? $due->dpng : $due->captain;

                if ($today >= $kindDue && $today <= $departureDate) {
                    $this->issue->first($departure, $kind);
                }
            }

            $incomplete = $passengers->contains(
                fn (ManifestPassenger $passenger): bool => ! $passenger->complete(),
            );
            $open = $this->openAlert($departure);

            if ($before && $today >= $due->dpng && $incomplete) {
                $this->raise->handle(
                    AlertKind::ManifestDataOverdue,
                    AlertKeys::manifestData($departure->id),
                    'Manifest data overdue',
                    'Passenger data for '.$departure->reference.' is incomplete after the DPNG due date.',
                    departureId: $departure->id,
                );
            } elseif ($before && ! $incomplete && $open instanceof Alert) {
                $this->resolve->handle($open, 'Passenger data is complete');
            }

            if ($before && $today >= $due->chase) {
                $this->chase($passengers);
            }
        });
    }

    private function resolveSailed(string $today): void
    {
        Alert::query()
            ->unresolved()
            ->where('kind', AlertKind::ManifestDataOverdue)
            ->whereHas('departure', function (Builder $query) use ($today): void {
                $query->where('date', '<=', $today);
            })
            ->orderBy('id')
            ->each(function (Alert $alert): void {
                $this->resolve->handle($alert, 'Departure sailed');
            });
    }

    /**
     * @param  Collection<int, ManifestPassenger>  $passengers
     */
    private function chase(Collection $passengers): void
    {
        $ids = $passengers
            ->filter(fn (ManifestPassenger $passenger): bool => ! $passenger->complete())
            ->map(fn (ManifestPassenger $passenger): int => $passenger->guest->booking_id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        Booking::query()
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->each(function (Booking $booking): void {
                $this->chasers->handle($booking);
            });
    }

    private function openAlert(Departure $departure): ?Alert
    {
        $alert = Alert::query()
            ->where('base_key', AlertKeys::manifestData($departure->id))
            ->whereNull('resolved_at')
            ->first();

        return $alert instanceof Alert ? $alert : null;
    }

    /**
     * @return Builder<Departure>
     */
    private function departuresOnOrAfter(string $today): Builder
    {
        $statuses = array_map(
            fn (BookingStatus $status): string => $status->value,
            ManifestRoster::COUNTED,
        );

        return Departure::query()
            ->where('date', '>=', $today)
            ->whereExists(function (QueryBuilder $query) use ($statuses): void {
                $query->selectRaw('1')
                    ->from('bookings')
                    ->join('guests', 'guests.booking_id', '=', 'bookings.id')
                    ->whereColumn('bookings.departure_id', 'departures.id')
                    ->whereNull('bookings.deleted_at')
                    ->whereIn('bookings.status', $statuses);
            })
            ->orderBy('id');
    }
}
