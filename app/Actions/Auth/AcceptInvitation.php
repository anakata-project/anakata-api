<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Action;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AcceptInvitation extends Action
{
    public function handle(Request $request, string $email, string $token, string $password): User
    {
        $email = Str::lower($email);
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->status !== UserStatus::Invited) {
            throw ValidationException::withMessages([
                'token' => __('passwords.token'),
            ]);
        }

        $this->transaction(function () use ($user, $email, $token, $password): void {
            $status = Password::broker('invitations')->reset(
                [
                    'email' => $email,
                    'token' => $token,
                    'password' => $password,
                    'password_confirmation' => $password,
                ],
                function (User $invited, string $newPassword) use ($user): void {
                    $invited->forceFill([
                        'password' => $newPassword,
                        'status' => UserStatus::Active,
                        'activated_at' => now(),
                    ])->save();

                    History::record(
                        $invited,
                        'user.activated',
                        ['status' => UserStatus::Invited->value],
                        ['status' => UserStatus::Active->value],
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

        $user->refresh();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::login($user, false);
        $request->session()->regenerate();

        return $user->fresh(['role']) ?? $user;
    }
}
