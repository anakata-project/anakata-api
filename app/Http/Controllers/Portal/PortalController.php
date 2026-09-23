<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyUser;
use Illuminate\Http\Request;

abstract class PortalController extends Controller
{
    protected function agency(Request $request): Agency
    {
        $agencyUser = $request->user('agency');

        if (! $agencyUser instanceof AgencyUser) {
            abort(401);
        }

        return $agencyUser->agency;
    }
}
