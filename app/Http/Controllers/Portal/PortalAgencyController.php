<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Resources\Portal\PortalAgencyMeResource;
use App\Models\AgencyUser;
use App\Services\Config\CurrentConfig;
use App\Support\Agencies\PortalPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PortalAgencyController extends PortalController
{
    public function me(Request $request): PortalAgencyMeResource
    {
        $agencyUser = $request->user('agency');

        if (! $agencyUser instanceof AgencyUser) {
            abort(401);
        }

        return new PortalAgencyMeResource($agencyUser);
    }

    public function rates(Request $request, CurrentConfig $config): JsonResponse
    {
        $agency = $this->agency($request);

        return response()->json([
            'data' => PortalPreview::for($agency, $config->rates())['net_rates'],
        ]);
    }
}
