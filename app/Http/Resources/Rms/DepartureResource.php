<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\ConfigKind;
use App\Models\Departure;
use App\Services\Config\CurrentConfig;
use App\Services\Inventory\Availability;
use App\Support\Inventory\DepartureSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Departure
 */
class DepartureResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['yacht', 'itinerary']);

        $year = (int) $this->date->format('Y');
        $method = $request->route()?->getActionMethod();
        $isDetail = in_array($method, ['show', 'layout', 'store', 'update'], true);
        $withCabins = $isDetail || $request->boolean('with_cabins');
        $withLocks = $isDetail;
        $snapshot = $this->snapshot();

        $payload = [
            'id' => $this->id,
            'reference' => $this->reference,
            'date' => $this->date->toDateString(),
            'return_date' => $this->returnDate()->toDateString(),
            'yacht_id' => $this->yacht_id,
            'itinerary_id' => $this->itinerary_id,
            'status' => $this->status->value,
            'urgency_threshold' => $this->urgency_threshold,
            'waitlist_enabled' => $this->waitlist_enabled,
            'public_note' => $this->public_note,
            'festive' => $this->festive,
            'yacht' => [
                'id' => $this->yacht->id,
                'code' => $this->yacht->code,
                'name' => $this->yacht->name,
            ],
            'itinerary' => [
                'id' => $this->itinerary->id,
                'code' => $this->itinerary->code,
                'name' => $this->itinerary->name,
                'status' => $this->itinerary->status->value,
                'festive' => $this->itinerary->festive,
            ],
            'rates' => [
                'year' => $year,
                'suite_from' => $this->suiteFrom($year),
            ],
            'availability' => [
                'counts' => $snapshot->counts,
                'engine_label' => $snapshot->engineLabel,
            ],
        ];

        if ($withCabins) {
            $payload['availability']['cabins'] = $snapshot->cabins;
        }

        if ($withLocks) {
            $payload['locks'] = $snapshot->locks;
        }

        return $payload;
    }

    private function snapshot(): DepartureSnapshot
    {
        if ($this->resource->snapshot instanceof DepartureSnapshot) {
            return $this->resource->snapshot;
        }

        $computed = app(Availability::class)->forDepartures(collect([$this->resource]));

        return $computed[$this->id];
    }

    private function suiteFrom(int $year): ?int
    {
        $config = app(CurrentConfig::class);

        if (! $config->has(ConfigKind::Rates)) {
            return null;
        }

        return $config->rates()->year($year)?->suitePp;
    }
}
