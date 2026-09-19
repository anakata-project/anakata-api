<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginUser
{
    /**
     * A valid bcrypt hash used only so unknown emails spend comparable time
     * in Hash::check as known emails. Never used as a real password.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function handle(Request $request, string $email, string $password): User
    {
        $email = Str::lower($email);
        $user = User::query()->where('email', $email)->first();

        $hash = is_string($user?->getAuthPassword()) && $user->getAuthPassword() !== ''
            ? $user->getAuthPassword()
            : self::DUMMY_PASSWORD_HASH;

        $passwordMatches = Hash::check($password, $hash);

        if ($user === null || $user->status !== UserStatus::Active || ! $passwordMatches) {
            Log::channel('security')->info('Failed login', [
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        Auth::login($user, false);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return $user->fresh(['role']) ?? $user;
    }
}
