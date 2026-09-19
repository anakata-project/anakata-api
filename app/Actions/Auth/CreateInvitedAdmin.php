<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Action;
use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Support\Str;
use RuntimeException;

final class CreateInvitedAdmin extends Action
{
    /**
     * @return array{0: User, 1: string}
     */
    public function handle(string $email, string $name): array
    {
        $email = Str::lower($email);

        if (User::query()->where('email', $email)->exists()) {
            throw new RuntimeException('A user with that email already exists.');
        }

        $admin = Role::query()->where('slug', SystemRole::Admin->value)->firstOrFail();

        return $this->transaction(function () use ($email, $name, $admin): array {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'role_id' => $admin->id,
                'status' => UserStatus::Invited,
                'invited_at' => now(),
                'password' => null,
            ]);

            History::record($user, 'user.invited', after: ['role' => $admin->name]);

            $url = app(SendUserInvitation::class)->handle($user);

            return [$user, $url];
        });
    }
}
