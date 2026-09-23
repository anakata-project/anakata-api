<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Actions\Action;
use App\Enums\AgencyUserStatus;
use App\Models\AgencyUser;
use App\Support\History\History;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AcceptAgencyInvitation extends Action
{
    public function handle(Request $request, string $email, string $token, string $password): AgencyUser
    {
        $email = Str::lower($email);
        $tokenHash = hash('sha256', $token);

        $agencyUser = AgencyUser::query()
            ->with('agency')
            ->where('email', $email)
            ->where('invite_token_hash', $tokenHash)
            ->first();

        if ($agencyUser === null || $agencyUser->invite_expires_at === null || $agencyUser->invite_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => __('passwords.token'),
            ]);
        }

        $this->transaction(function () use ($agencyUser, $password): void {
            $agencyUser->forceFill([
                'password' => $password,
                'status' => AgencyUserStatus::Active,
                'accepted_at' => now(),
                'invite_token_hash' => null,
                'invite_sent_at' => null,
                'invite_expires_at' => null,
            ])->save();

            History::record($agencyUser->agency, 'portal.accepted', before: [
                'status' => AgencyUserStatus::InviteOnPortalLaunch->value,
            ], after: [
                'status' => AgencyUserStatus::Active->value,
            ], actorLabel: "{$agencyUser->name} ({$agencyUser->email})", extraContext: [
                'agency_user_id' => $agencyUser->id,
            ]);
        });

        $agencyUser->refresh();

        Auth::guard('agency')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::guard('agency')->login($agencyUser, false);
        $request->session()->regenerate();

        return $agencyUser->fresh(['agency']) ?? $agencyUser;
    }
}
