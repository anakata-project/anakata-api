<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Actions\Action;
use App\Enums\ReleaseReason;
use App\Models\Booking;
use App\Models\User;
use App\Services\Inventory\ClaimService;
use App\Support\Bookings\BookingMutationLock;
use App\Support\History\History;

final class DeleteBooking extends Action
{
    public function __construct(private ClaimService $claims) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Booking $booking, array $data, User $actor): void
    {
        $this->transaction(function () use ($booking, $data, $actor): void {
            $expectedDepartureId = (int) $booking->departure_id;
            $booking = BookingMutationLock::acquire($booking, $expectedDepartureId);
            $booking->load('contact');

            $reason = trim((string) ($data['reason'] ?? ''));

            $this->claims->release($booking, ReleaseReason::Cancelled);

            History::record($booking, 'booking.deleted', after: [
                'client' => $booking->contact->name,
                'what' => 'Reservation deleted',
                'reference' => $booking->displayReference(),
            ], reason: $reason, actor: $actor);

            $booking->delete();
        });
    }
}
