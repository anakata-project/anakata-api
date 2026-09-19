<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Action;
use App\Actions\Auth\SendUserInvitation;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class InviteUser extends Action
{
    public function handle(string $name, string $email, Role $role): User
    {
        $email = Str::lower($email);
        $actor = Auth::user();
        $inviterName = $actor instanceof User ? $actor->name : null;

        return $this->transaction(function () use ($name, $email, $role, $inviterName): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'role_id' => $role->id,
                'status' => UserStatus::Invited,
                'invited_at' => now(),
                'password' => null,
            ]);

            History::record($user, 'user.invited', after: ['role' => $role->name]);

            app(SendUserInvitation::class)->handle($user, $inviterName);

            return $user->fresh(['role']) ?? $user;
        });
    }
}
