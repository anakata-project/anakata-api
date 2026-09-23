<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Actions\Action;
use App\Models\AgencyUser;
use App\Support\History\History;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LogoutAgencyUser extends Action
{
    public function handle(Request $request): void
    {
        $agencyUser = Auth::guard('agency')->user();

        Auth::guard('agency')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($agencyUser instanceof AgencyUser) {
            $agencyUser->loadMissing('agency');

            $this->transaction(function () use ($agencyUser): void {
                History::record($agencyUser->agency, 'portal.signed_out', actorLabel: "{$agencyUser->name} ({$agencyUser->email})", extraContext: [
                    'agency_user_id' => $agencyUser->id,
                ]);
            });
        }
    }
}
