<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Action;
use App\Enums\UserStatus;
use App\Exceptions\ConflictException;
use App\Models\User;
use App\Support\History\History;
use App\Support\Users\LastAdminGuard;

final class DisableUser extends Action
{
    public function handle(User $actor, User $user, ?string $reason = null): User
    {
        if ($user->status === UserStatus::Disabled) {
            return $user;
        }

        return $this->transaction(function () use ($actor, $user, $reason): User {
            LastAdminGuard::assertCanLoseAdmin($user);

            if ($actor->id === $user->id) {
                throw new ConflictException('You cannot disable your own account.');
            }

            $user->forceFill([
                'status' => UserStatus::Disabled,
                'disabled_at' => now(),
            ])->save();

            History::record($user, 'user.disabled', reason: $reason);

            return $user;
        });
    }
}
