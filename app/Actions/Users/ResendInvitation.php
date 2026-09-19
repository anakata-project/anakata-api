<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Action;
use App\Actions\Auth\SendUserInvitation;
use App\Enums\UserStatus;
use App\Exceptions\ConflictException;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Support\Facades\Auth;

final class ResendInvitation extends Action
{
    public function handle(User $user): User
    {
        if ($user->status !== UserStatus::Invited) {
            throw new ConflictException('An invitation can only be resent to an invited user.');
        }

        $actor = Auth::user();
        $inviterName = $actor instanceof User ? $actor->name : null;

        return $this->transaction(function () use ($user, $inviterName): User {
            History::record($user, 'user.invitation_resent');

            app(SendUserInvitation::class)->handle($user, $inviterName);

            return $user;
        });
    }
}
