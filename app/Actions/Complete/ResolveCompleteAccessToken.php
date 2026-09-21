<?php

declare(strict_types=1);

namespace App\Actions\Complete;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingAccessToken;
use App\Support\Complete\CompleteAccess;

final class ResolveCompleteAccessToken
{
    public function handle(string $token): BookingAccessToken
    {
        $row = BookingAccessToken::findByToken($token);

        if (! $row instanceof BookingAccessToken || ! $row->isActive()) {
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
}
