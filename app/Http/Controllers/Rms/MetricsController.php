<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\MetricsIndexRequest;
use App\Http\Resources\Rms\MetricsResource;
use App\Models\User;
use App\Support\Metrics\CommercialMetrics;

final class MetricsController extends Controller
{
    public function __invoke(MetricsIndexRequest $request, CommercialMetrics $metrics): MetricsResource
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new MetricsResource($metrics->present(
            $request->window(),
            $request->scope(),
            $actor,
        ));
    }
}
