<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Departures\CreateDeparture;
use App\Actions\Departures\DeleteDeparture;
use App\Actions\Departures\GenerateSeason;
use App\Actions\Departures\UpdateDeparture;
use App\Enums\DepartureStatus;
use App\Enums\SeasonPattern;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\GenerateSeasonRequest;
use App\Http\Requests\Rms\IndexDeparturesRequest;
use App\Http\Requests\Rms\StoreDepartureRequest;
use App\Http\Requests\Rms\UpdateDepartureRequest;
use App\Http\Resources\Rms\ChangeHistoryResource;
use App\Http\Resources\Rms\DepartureResource;
use App\Models\Departure;
use App\Models\Yacht;
use App\Support\Departures\Warnings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class DepartureController extends Controller
{
    public function index(IndexDeparturesRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Departure::class);

        $perPage = $request->integer('per_page', 100);

        $departures = Departure::query()
            ->with(['yacht', 'itinerary'])
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('date', '>=', (string) $request->validated('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('date', '<=', (string) $request->validated('to')))
            ->when($request->filled('yacht_id'), fn (Builder $query) => $query->where('yacht_id', $request->validated('yacht_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->validated('status')))
            ->orderBy('date')
            ->orderBy(
                Yacht::query()->select('code')->whereColumn('yachts.id', 'departures.yacht_id'),
            )
            ->paginate($perPage);

        return DepartureResource::collection($departures);
    }

    public function show(Departure $departure): DepartureResource
    {
        $this->authorize('view', $departure);

        return new DepartureResource($departure);
    }

    public function store(StoreDepartureRequest $request, CreateDeparture $action): JsonResponse
    {
        $this->authorize('create', Departure::class);

        $departure = $action->handle($request->validated());

        return response()->json([
            ...((new DepartureResource($departure))->resolve()),
            'warnings' => Warnings::for($departure),
        ], 201);
    }

    public function update(UpdateDepartureRequest $request, Departure $departure, UpdateDeparture $action): JsonResponse
    {
        $this->authorize('update', $departure);

        $updated = $action->handle($departure, $request->validated());

        return response()->json([
            ...((new DepartureResource($updated))->resolve()),
            'warnings' => Warnings::for($updated),
        ]);
    }

    public function destroy(Departure $departure, DeleteDeparture $action): Response
    {
        $this->authorize('delete', $departure);

        $action->handle($departure);

        return response()->noContent();
    }

    public function generate(GenerateSeasonRequest $request, GenerateSeason $action): JsonResponse
    {
        $this->authorize('generate', Departure::class);

        $validated = $request->validated();

        $result = $action->handle(
            CreateDeparture::calendarDate($validated['from']),
            CreateDeparture::calendarDate($validated['to']),
            array_map(intval(...), $validated['yacht_ids']),
            SeasonPattern::from((string) $validated['pattern']),
            (bool) $validated['festive_window'],
            DepartureStatus::from((string) $validated['status']),
        );

        return response()->json($result);
    }

    public function history(Departure $departure): AnonymousResourceCollection
    {
        $this->authorize('viewHistory', $departure);

        $entries = $departure->history()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return ChangeHistoryResource::collection($entries);
    }
}
