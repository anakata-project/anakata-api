<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Support\Agencies\PortalPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PortalSalesMaterialController extends PortalController
{
    public function index(Request $request): JsonResponse
    {
        $this->agency($request);

        return response()->json([
            'data' => [],
            'meta' => [
                'note' => PortalPreview::MATERIALS_NOTE,
            ],
        ]);
    }
}
