<?php

declare(strict_types=1);

namespace App\Support\Engine;

use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Models\User;
use RuntimeException;

final class EngineBookingOwner
{
    public static function user(): User
    {
        $user = User::query()
            ->where('status', UserStatus::Active)
            ->whereNull('disabled_at')
            ->whereHas('role', fn ($query) => $query->where('slug', SystemRole::Admin->value))
            ->orderBy('id')
            ->first();

        if (! $user instanceof User) {
            throw new RuntimeException('No active Admin is available to own engine bookings.');
        }

        return $user;
    }
}
