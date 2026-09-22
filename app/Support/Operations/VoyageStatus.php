<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Actions\Alerts\RaiseAlert;
use App\Actions\Bookings\TransitionBooking;
use App\Enums\AlertKind;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Alerts\AlertKeys;
use App\Support\BusinessTime;
use App\Support\Crm\ContactDerived;
use App\Support\Crm\TaskSweep;
use Illuminate\Database\Eloquent\Builder;

final class VoyageStatus
{
    public const ACTOR = 'System · voyage status';

    public function __construct(
        private readonly TransitionBooking $transitions,
        private readonly RaiseAlert $raise,
    ) {}

    public function run(): void
    {
        $today = BusinessTime::now()->toDateString();

        $this->move($this->fullyPaidDue($today)->orderBy('bookings.id'), BookingStatus::OnBoard);

        foreach ($this->onBoardDue($today)->orderBy('bookings.id')->get() as $booking) {
            $this->transition($booking, BookingStatus::Completed);
        }

        $this->raiseStuck($today);
    }

    /**
     * @param  Builder<Booking>  $query
     */
    private function move(Builder $query, BookingStatus $to): void
    {
        $query->each(function (Booking $booking) use ($to): void {
            $this->transition($booking, $to);
        });
    }

    private function transition(Booking $booking, BookingStatus $to): void
    {
        $this->transitions->handle($booking, [
            'to' => $to,
        ], null, system: true, actorLabel: self::ACTOR);
    }

    /**
     * @return Builder<Booking>
     */
    private function fullyPaidDue(string $today): Builder
    {
        return Booking::query()
            ->where('bookings.status', BookingStatus::FullyPaid)
            ->whereHas('departure', function (Builder $departure) use ($today): void {
                $departure->where('date', '<=', $today);
            });
    }

    /**
     * @return Builder<Booking>
     */
    private function onBoardDue(string $today): Builder
    {
        return Booking::query()
            ->select('bookings.*')
            ->join('departures', 'departures.id', '=', 'bookings.departure_id')
            ->join('itineraries', 'itineraries.id', '=', 'departures.itinerary_id')
            ->where('bookings.status', BookingStatus::OnBoard->value)
            ->whereRaw(ContactDerived::returnDateSql().' <= ?', [$today]);
    }

    private function raiseStuck(string $today): void
    {
        Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::OnHoldAgency])
            ->whereHas('departure', function (Builder $departure) use ($today): void {
                $departure->where('date', '<=', $today);
            })
            ->orderBy('id')
            ->each(function (Booking $booking): void {
                $reference = TaskSweep::reference($booking);
                $status = $booking->status->value;

                $this->raise->handle(
                    AlertKind::ConfirmedAtDeparture,
                    AlertKeys::confirmedAtDeparture($booking->id),
                    'Confirmed at departure '.$reference,
                    $reference.' is still '.$status.' on its departure date.',
                    bookingId: $booking->id,
                );
            });
    }
}
