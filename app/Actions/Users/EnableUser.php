<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Action;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\History\History;

final class EnableUser extends Action
{
    public function handle(User $user): User
    {
        if ($user->status !== UserStatus::Disabled) {
            return $user;
        }

        return $this->transaction(function () use ($user): User {
            $user->forceFill([
                'status' => $user->activated_at === null
                    ? UserStatus::Invited
                    : UserStatus::Active,
                'disabled_at' => null,
            ])->save();

            History::record($user, 'user.enabled');

            return $user;
        });
    }
}
