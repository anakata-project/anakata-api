<?php

declare(strict_types=1);

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Validation\ValidationException;

final class BookingCharges
{
    public static function assertWritable(Booking $booking): void
    {
        if (in_array($booking->status, [
            BookingStatus::Cancelled,
            BookingStatus::CancelledPostpaid,
            BookingStatus::Released,
        ], true)) {
            throw ValidationException::withMessages([
                'booking' => ['Extras and fees cannot be changed on a cancelled or released booking.'],
            ]);
        }
    }
}
