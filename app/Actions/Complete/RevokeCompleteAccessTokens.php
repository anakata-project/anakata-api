<?php

declare(strict_types=1);

namespace App\Actions\Complete;

use App\Actions\Action;
use App\Enums\BookingAccessTokenPurpose;
use App\Models\Booking;
use App\Models\BookingAccessToken;

final class RevokeCompleteAccessTokens extends Action
{
    public function handle(Booking $booking): void
    {
        BookingAccessToken::query()
            ->where('booking_id', $booking->id)
            ->where('purpose', BookingAccessTokenPurpose::Complete)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
