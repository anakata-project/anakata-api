<?php

declare(strict_types=1);

namespace App\Actions\GuestExperience;

use App\Enums\BookingAccessTokenPurpose;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingAccessToken;
use App\Models\Guest;
use App\Support\Complete\CompleteAccess;

final class ResolveSurveyAccessToken
{
    public function handle(string $token): BookingAccessToken
    {
        $row = BookingAccessToken::findByToken($token);

        if (! $row instanceof BookingAccessToken
            || ! $row->isActive()
            || $row->purpose !== BookingAccessTokenPurpose::Survey
        ) {
            CompleteAccess::abortNotFound();
        }

        $booking = Booking::query()->withTrashed()->find($row->booking_id);

        if (! $booking instanceof Booking
            || $booking->trashed()
            || in_array($booking->status, [
                BookingStatus::Cancelled,
                BookingStatus::CancelledPostpaid,
                BookingStatus::Released,
            ], true)
        ) {
            CompleteAccess::abortNotFound();
        }

        $row->setRelation('booking', $booking);

        return $row;
    }

    public function guest(BookingAccessToken $token, int $guestId): Guest
    {
        $covered = array_map(intval(...), $token->covered_guest_ids ?? []);

        if (! in_array($guestId, $covered, true)) {
            CompleteAccess::abortNotFound();
        }

        $guest = Guest::query()
            ->where('booking_id', $token->booking_id)
            ->whereKey($guestId)
            ->first();

        if (! $guest instanceof Guest) {
            CompleteAccess::abortNotFound();
        }

        return $guest;
    }
}
