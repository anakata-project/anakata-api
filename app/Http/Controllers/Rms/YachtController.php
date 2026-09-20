<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Rms\YachtResource;
use App\Models\Yacht;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class YachtController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Yacht::class);

        $yachts = Yacht::query()
            ->with('cabins')
            ->orderBy('code')
            ->get();

        return YachtResource::collection($yachts);
    }
}
