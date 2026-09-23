<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Enums\AgencyUserStatus;
use App\Models\AgencyUser;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class SendAgencyPasswordReset
{
    public function handle(string $email): void
    {
        $email = Str::lower($email);
        $agencyUser = AgencyUser::query()->where('email', $email)->first();

        if ($agencyUser === null || $agencyUser->status !== AgencyUserStatus::Active) {
            return;
        }

        Password::broker('agency_users')->sendResetLink(['email' => $agencyUser->email]);
    }
}
