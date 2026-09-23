<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Actions\Action;
use App\Enums\AgencyStatus;
use App\Enums\AgencyUserStatus;
use App\Models\AgencyUser;
use App\Support\History\History;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginAgencyUser extends Action
{
    /**
     * A valid bcrypt hash used only so unknown emails spend comparable time
     * in Hash::check as known emails. Never used as a real password.
     */
    private static ?string $dummyPasswordHash = null;

    public function handle(Request $request, string $email, string $password): AgencyUser
    {
        $email = Str::lower($email);
        $agencyUser = AgencyUser::query()->with('agency')->where('email', $email)->first();

        $hash = is_string($agencyUser?->getAuthPassword()) && $agencyUser->getAuthPassword() !== ''
            ? $agencyUser->getAuthPassword()
            : self::dummyPasswordHash();

        $passwordMatches = Hash::check($password, $hash);

        $valid = $agencyUser !== null
            && $agencyUser->status === AgencyUserStatus::Active
            && $agencyUser->password !== null
            && $agencyUser->agency->status === AgencyStatus::Approved
            && ! $agencyUser->agency->isPortalSuspended()
            && $passwordMatches;

        if (! $valid) {
            if ($agencyUser instanceof AgencyUser) {
                $this->transaction(function () use ($agencyUser): void {
                    History::record($agencyUser->agency, 'portal.sign_in_failed', reason: $this->failureReason($agencyUser), actorLabel: "{$agencyUser->name} ({$agencyUser->email})", extraContext: [
                        'agency_user_id' => $agencyUser->id,
                    ]);
                });
            }

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        Auth::guard('agency')->login($agencyUser, false);
        $request->session()->regenerate();

        $agencyUser->forceFill(['last_login_at' => now()])->save();

        $this->transaction(function () use ($agencyUser): void {
            History::record($agencyUser->agency, 'portal.signed_in', actorLabel: "{$agencyUser->name} ({$agencyUser->email})", extraContext: [
                'agency_user_id' => $agencyUser->id,
            ]);
        });

        return $agencyUser->fresh(['agency']) ?? $agencyUser;
    }

    private function failureReason(AgencyUser $agencyUser): string
    {
        return match (true) {
            $agencyUser->status !== AgencyUserStatus::Active => 'user is not active',
            $agencyUser->password === null => 'user has not accepted an invitation',
            $agencyUser->agency->status !== AgencyStatus::Approved => 'agency is not approved',
            $agencyUser->agency->isPortalSuspended() => 'agency portal access is suspended',
            default => 'wrong password',
        };
    }

    private static function dummyPasswordHash(): string
    {
        return self::$dummyPasswordHash ??= Hash::make(Str::random(32));
    }
}
