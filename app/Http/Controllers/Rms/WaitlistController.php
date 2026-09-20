<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Waitlist\AddWaitlistEntry;
use App\Actions\Waitlist\NotifyWaitlistEntry;
use App\Actions\Waitlist\RemoveWaitlistEntry;
use App\Enums\CabinCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\IndexWaitlistRequest;
use App\Http\Requests\Rms\NotifyWaitlistEntryRequest;
use App\Http\Requests\Rms\RemoveWaitlistEntryRequest;
use App\Http\Requests\Rms\StoreWaitlistEntryRequest;
use App\Http\Resources\Rms\WaitlistEntryResource;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Inventory\Availability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WaitlistController extends Controller
{
    public function index(IndexWaitlistRequest $request, Availability $availability): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WaitlistEntry::class);

        $entries = WaitlistEntry::query()
            ->with(['departure.yacht', 'contact', 'notifiedBy'])
            ->when(
                ! $request->boolean('include_removed'),
                fn (Builder $query) => $query->active(),
            )
            ->when(
                $request->filled('departure_id'),
                fn (Builder $query) => $query->where('departure_id', $request->validated('departure_id')),
            )
            ->when(
                $request->filled('from') || $request->filled('to'),
                function (Builder $query) use ($request): void {
                    $query->whereHas('departure', function (Builder $departure) use ($request): void {
                        $departure
                            ->when($request->filled('from'), fn (Builder $inner) => $inner->whereDate('date', '>=', (string) $request->validated('from')))
                            ->when($request->filled('to'), fn (Builder $inner) => $inner->whereDate('date', '<=', (string) $request->validated('to')));
                    });
                },
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $ranks = $this->positions($entries->pluck('departure_id')->unique()->all());
        $departures = $entries->pluck('departure')->unique('id')->values();
        $snapshots = $availability->forDepartures($departures);

        foreach ($entries as $entry) {
            $entry->queuePosition = $entry->isActive() ? ($ranks[$entry->id] ?? null) : null;
            $counts = $snapshots[$entry->departure_id]->counts;
            $entry->cabinIsAvailable = $entry->cabin_category === CabinCategory::Owner
                ? $counts['owner_free']
                : $counts['suites_free'] > 0;
        }

        return WaitlistEntryResource::collection($entries);
    }

    public function store(StoreWaitlistEntryRequest $request, AddWaitlistEntry $action): JsonResponse
    {
        $this->authorize('create', WaitlistEntry::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $entry = $action->handle($request->validated(), $actor);
        $entry->queuePosition = 1;
        $entry->cabinIsAvailable = false;

        return (new WaitlistEntryResource($entry))->response()->setStatusCode(201);
    }

    public function notify(
        NotifyWaitlistEntryRequest $request,
        WaitlistEntry $entry,
        NotifyWaitlistEntry $action,
    ): WaitlistEntryResource {
        $this->authorize('notify', $entry);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new WaitlistEntryResource($action->handle($entry, $request->validated(), $actor));
    }

    public function remove(
        RemoveWaitlistEntryRequest $request,
        WaitlistEntry $entry,
        RemoveWaitlistEntry $action,
    ): WaitlistEntryResource {
        $this->authorize('remove', $entry);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new WaitlistEntryResource($action->handle($entry, $request->validated(), $actor));
    }

    /**
     * @param  list<int|string>  $departureIds
     * @return array<int, int>
     */
    private function positions(array $departureIds): array
    {
        if ($departureIds === []) {
            return [];
        }

        $actives = WaitlistEntry::query()
            ->active()
            ->whereIn('departure_id', $departureIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $ranks = [];

        foreach ($actives->groupBy(fn (WaitlistEntry $entry): string => $entry->departure_id.'|'.$entry->cabin_category->value) as $group) {
            foreach ($group->values() as $index => $entry) {
                $ranks[$entry->id] = $index + 1;
            }
        }

        return $ranks;
    }
}
