<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Actions\Charter\CreateCharterEnquiry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engine\StoreEngineCharterEnquiryRequest;
use App\Http\Resources\Engine\EngineCharterEnquiryResource;
use App\Models\Departure;
use App\Services\Engine\EngineFeed;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CharterEnquiryController extends Controller
{
    #[DocumentedResponse(status: 201, type: EngineCharterEnquiryResource::class)]
    public function __invoke(
        StoreEngineCharterEnquiryRequest $request,
        EngineFeed $feed,
        CreateCharterEnquiry $action,
    ): JsonResponse {
        $validated = $request->validated();

        if (isset($validated['departure_id'])) {
            $departure = Departure::query()->findOrFail((int) $validated['departure_id']);
            abort_unless($feed->isVisible($departure), Response::HTTP_NOT_FOUND);
        }

        $enquiry = $action->handle($validated);

        return (new EngineCharterEnquiryResource($enquiry))->response()->setStatusCode(201);
    }
}
