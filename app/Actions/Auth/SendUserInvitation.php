<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Password;

final class SendUserInvitation
{
    public function handle(User $user, ?string $inviterName = null): string
    {
        $user->loadMissing('role');

        $token = Password::broker('invitations')->createToken($user);

        $role = $user->role;

        $user->notify(new UserInvitation(
            $token,
            $inviterName,
            $role instanceof Role ? $role->name : '',
        ));

        return self::url($user, $token);
    }

    public static function url(object $notifiable, string $token): string
    {
        $email = $notifiable instanceof User
            ? $notifiable->getEmailForPasswordReset()
            : (string) $notifiable->email;

        return rtrim((string) config('anakata.panel_url'), '/').'/accept-invitation?'.http_build_query([
            'token' => $token,
            'email' => $email,
        ]);
    }
}
