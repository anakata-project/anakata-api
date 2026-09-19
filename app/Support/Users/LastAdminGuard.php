<?php

declare(strict_types=1);

namespace App\Support\Users;

use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Exceptions\ConflictException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class LastAdminGuard
{
    public static function assertCanLoseAdmin(User $target): void
    {
        $target->loadMissing('role');

        if (! $target->role->isAdmin()) {
            return;
        }

        if ($target->status !== UserStatus::Active) {
            return;
        }

        $activeAdmins = User::query()
            ->where('status', UserStatus::Active)
            ->whereHas('role', function (Builder $query): void {
                $query->where('slug', SystemRole::Admin->value);
            })
            ->lockForUpdate()
            ->get();

        $hasOther = $activeAdmins->contains(
            fn (User $admin): bool => $admin->id !== $target->id,
        );

        if (! $hasOther) {
            throw new ConflictException('This would leave no active admin.');
        }
    }
}
