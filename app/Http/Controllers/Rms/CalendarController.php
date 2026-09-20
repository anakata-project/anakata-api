<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\IndexCalendarRequest;
use App\Models\Departure;
use App\Models\Yacht;
use App\Support\Inventory\Snapshots;
use Illuminate\Http\JsonResponse;

final class CalendarController extends Controller
{
    public function __invoke(IndexCalendarRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Departure::class);

        [$from, $to] = $request->range();

        $yachts = Yacht::query()
            ->with('cabins')
            ->when($request->filled('yacht_id'), fn ($query) => $query->whereKey($request->validated('yacht_id')))
            ->orderBy('code')
            ->get();

        $departures = Departure::query()
            ->with(['yacht', 'itinerary'])
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->when($request->filled('yacht_id'), fn ($query) => $query->where('yacht_id', $request->validated('yacht_id')))
            ->orderBy('date')
            ->orderBy(
                Yacht::query()->select('code')->whereColumn('yachts.id', 'departures.yacht_id'),
            )
            ->get();

        $snapshots = Snapshots::attach($departures);

        $rows = [];

        foreach ($yachts as $yacht) {
            foreach ($yacht->cabins as $cabin) {
                $cells = [];

                foreach ($departures as $departure) {
                    if ($departure->yacht_id !== $yacht->id) {
                        continue;
                    }

                    $cabinRow = collect($snapshots[$departure->id]->cabins)
                        ->firstWhere('cabin.code', $cabin->code);

                    $cells[(string) $departure->id] = [
                        'state' => $cabinRow['state'] ?? 'FREE',
                        'claim' => $cabinRow['claim'] ?? null,
                    ];
                }

                $rows[] = [
                    'yacht' => [
                        'id' => $yacht->id,
                        'code' => $yacht->code,
                        'name' => $yacht->name,
                    ],
                    'cabin' => [
                        'id' => $cabin->id,
                        'code' => $cabin->code,
                        'label' => $cabin->label,
                        'category' => $cabin->category->value,
                        'sort' => $cabin->sort,
                    ],
                    'cells' => $cells,
                ];
            }
        }

        return response()->json([
            'departures' => $departures->map(fn (Departure $departure): array => [
                'id' => $departure->id,
                'reference' => $departure->reference,
                'date' => $departure->date->toDateString(),
                'yacht' => [
                    'id' => $departure->yacht->id,
                    'code' => $departure->yacht->code,
                    'name' => $departure->yacht->name,
                ],
                'itinerary' => [
                    'id' => $departure->itinerary->id,
                    'code' => $departure->itinerary->code,
                    'name' => $departure->itinerary->name,
                ],
                'festive' => $departure->festive,
                'status' => $departure->status->value,
            ])->values(),
            'rows' => $rows,
        ]);
    }
}
