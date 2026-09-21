<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Actions\Engine\IngestBehaviouralEvents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engine\StoreEngineEventsRequest;
use App\Http\Resources\Engine\EngineEventsAcceptedResource;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;

final class EngineEventsController extends Controller
{
    #[DocumentedResponse(status: 200, type: EngineEventsAcceptedResource::class)]
    public function __invoke(
        StoreEngineEventsRequest $request,
        IngestBehaviouralEvents $action,
    ): EngineEventsAcceptedResource {
        return new EngineEventsAcceptedResource($action->handle($request->validated()));
    }
}
