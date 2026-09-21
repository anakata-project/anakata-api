<?php

declare(strict_types=1);

namespace App\Actions\Guests;

use App\Actions\Action;
use App\Events\BookingChargesChanged;
use App\Models\Guest;
use App\Models\User;
use App\Support\Bookings\BookingMutationLock;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class RemoveGuest extends Action
{
    public function handle(Guest $guest, User $actor): void
    {
        $this->transaction(function () use ($guest, $actor): void {
            $guest->load('booking.departure');
            $booking = BookingMutationLock::acquire($guest->booking, (int) $guest->booking->departure_id);

            if ($guest->is_lead || $guest->first_name !== '' || $guest->last_name !== '') {
                throw ValidationException::withMessages([
                    'guest' => ['Only an empty non-lead guest can be removed.'],
                ]);
            }

            $feeBefore = (int) $guest->png_fee;
            $guest->delete();

            History::record($booking, 'guest.removed', before: [
                'guest_id' => $guest->id,
                'position' => $guest->position,
            ], after: [
                'what' => 'Empty guest slot removed ('.$booking->guests()->count().' guests)',
            ], actor: $actor);

            if ($booking->png_collected && $feeBefore > 0) {
                BookingChargesChanged::dispatch($booking, 'PNG fee changed');
            }
        });
    }
}
