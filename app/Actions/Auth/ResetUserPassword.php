<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Action;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ResetUserPassword extends Action
{
    public function handle(string $email, string $token, string $password): void
    {
        $email = Str::lower($email);
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'token' => __('passwords.token'),
            ]);
        }

        $this->transaction(function () use ($user, $email, $token, $password): void {
            $status = Password::broker()->reset(
                [
                    'email' => $email,
                    'token' => $token,
                    'password' => $password,
                    'password_confirmation' => $password,
                ],
                function (User $resetUser, string $newPassword) use ($user): void {
                    $resetUser->forceFill(['password' => $newPassword])->save();

                    History::record(
                        $resetUser,
                        'user.password_reset',
                        actor: $user,
                    );
                },
            );

            if ($status !== Password::PASSWORD_RESET) {
                throw ValidationException::withMessages([
                    'token' => __('passwords.token'),
                ]);
            }
        });
    }
}
