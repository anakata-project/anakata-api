<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class SendPasswordReset
{
    public function handle(string $email): void
    {
        $email = Str::lower($email);
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->status !== UserStatus::Active) {
            return;
        }

        Password::broker()->sendResetLink(['email' => $user->email]);
    }
}
