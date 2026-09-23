<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Actions\Action;
use App\Enums\AgencyUserStatus;
use App\Models\AgencyUser;
use App\Support\History\History;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ResetAgencyUserPassword extends Action
{
    public function handle(string $email, string $token, string $password): void
    {
        $email = Str::lower($email);
        $agencyUser = AgencyUser::query()->where('email', $email)->first();

        if ($agencyUser === null || $agencyUser->status !== AgencyUserStatus::Active) {
            throw ValidationException::withMessages([
                'token' => __('passwords.token'),
            ]);
        }

        $this->transaction(function () use ($email, $token, $password): void {
            $status = Password::broker('agency_users')->reset(
                [
                    'email' => $email,
                    'token' => $token,
                    'password' => $password,
                    'password_confirmation' => $password,
                ],
                function (AgencyUser $resetUser, string $newPassword): void {
                    $resetUser->forceFill(['password' => $newPassword])->save();

                    History::record($resetUser->agency, 'portal.password_reset', actorLabel: "{$resetUser->name} ({$resetUser->email})", extraContext: [
                        'agency_user_id' => $resetUser->id,
                    ]);
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
