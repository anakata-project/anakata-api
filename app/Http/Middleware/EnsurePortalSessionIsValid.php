<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AgencyStatus;
use App\Enums\AgencyUserStatus;
use App\Models\AgencyUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePortalSessionIsValid
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $agencyUser = Auth::guard('agency')->user();

        $invalid = ! $agencyUser instanceof AgencyUser
            || $agencyUser->status !== AgencyUserStatus::Active
            || $agencyUser->password === null
            || $agencyUser->agency->status !== AgencyStatus::Approved
            || $agencyUser->agency->isPortalSuspended();

        if ($invalid) {
            if ($agencyUser instanceof AgencyUser) {
                Auth::guard('agency')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
