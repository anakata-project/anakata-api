<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Actions\Waitlist\AddWaitlistEntry;
use App\Enums\WaitlistSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engine\StoreEngineWaitlistRequest;
use App\Http\Resources\Engine\EngineWaitlistResource;
use App\Models\Departure;
use App\Services\Engine\EngineFeed;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class WaitlistController extends Controller
{
    #[DocumentedResponse(status: 201, type: EngineWaitlistResource::class)]
    public function __invoke(
        StoreEngineWaitlistRequest $request,
        EngineFeed $feed,
        AddWaitlistEntry $action,
    ): JsonResponse {
        $validated = $request->validated();
        $departure = Departure::query()->findOrFail((int) $validated['departure_id']);

        abort_unless($feed->isVisible($departure), Response::HTTP_NOT_FOUND);

        $entry = $action->handle([
            'departure_id' => $departure->id,
            'cabin_category' => $validated['cabin_category'],
            'client' => is_array($validated['contact'] ?? null) ? $validated['contact'] : [],
            'adults' => $validated['adults'],
            'children' => $validated['children'],
            'notes' => $validated['notes'] ?? null,
            'source' => WaitlistSource::Engine,
        ]);

        return (new EngineWaitlistResource($entry))->response()->setStatusCode(201);
    }
}
