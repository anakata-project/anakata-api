<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Itineraries\CreateItinerary;
use App\Actions\Itineraries\DeleteItinerary;
use App\Actions\Itineraries\ReplaceItineraryImage;
use App\Actions\Itineraries\UpdateItinerary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\StoreItineraryImageRequest;
use App\Http\Requests\Rms\StoreItineraryRequest;
use App\Http\Requests\Rms\UpdateItineraryRequest;
use App\Http\Resources\Rms\ChangeHistoryResource;
use App\Http\Resources\Rms\ItineraryDefaultsResource;
use App\Http\Resources\Rms\ItineraryResource;
use App\Models\Itinerary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

final class ItineraryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Itinerary::class);

        $itineraries = Itinerary::query()
            ->withCount('departures')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ItineraryResource::collection($itineraries);
    }

    public function defaults(): ItineraryDefaultsResource
    {
        $this->authorize('viewAny', Itinerary::class);

        return new ItineraryDefaultsResource(null);
    }

    public function show(Itinerary $itinerary): ItineraryResource
    {
        $this->authorize('view', $itinerary);

        return new ItineraryResource($itinerary);
    }

    public function store(StoreItineraryRequest $request, CreateItinerary $action): JsonResponse
    {
        $this->authorize('create', Itinerary::class);

        $itinerary = $action->handle($request->validated());

        return (new ItineraryResource($itinerary))->response()->setStatusCode(201);
    }

    public function update(UpdateItineraryRequest $request, Itinerary $itinerary, UpdateItinerary $action): ItineraryResource
    {
        $this->authorize('update', $itinerary);

        return new ItineraryResource($action->handle($itinerary, $request->validated()));
    }

    public function image(StoreItineraryImageRequest $request, Itinerary $itinerary, ReplaceItineraryImage $action): ItineraryResource
    {
        $this->authorize('update', $itinerary);

        $file = $request->file('image');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        return new ItineraryResource($action->handle($itinerary, $file));
    }

    public function destroy(Itinerary $itinerary, DeleteItinerary $action): Response
    {
        $this->authorize('delete', $itinerary);

        $action->handle($itinerary);

        return response()->noContent();
    }

    public function history(Itinerary $itinerary): AnonymousResourceCollection
    {
        $this->authorize('viewHistory', $itinerary);

        $entries = $itinerary->history()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return ChangeHistoryResource::collection($entries);
    }
}
