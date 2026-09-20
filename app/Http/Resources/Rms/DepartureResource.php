<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\ConfigKind;
use App\Models\Departure;
use App\Services\Config\CurrentConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Task 03 extends this resource with availability, engine_label and locks.
 *
 * @mixin Departure
 */
class DepartureResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     reference: string,
     *     date: string,
     *     return_date: string,
     *     yacht_id: int,
     *     itinerary_id: int,
     *     status: string,
     *     urgency_threshold: int,
     *     waitlist_enabled: bool,
     *     public_note: string|null,
     *     festive: bool,
     *     yacht: array{id: int, code: string, name: string},
     *     itinerary: array{id: int, code: string, name: string, status: string, festive: bool},
     *     rates: array{year: int, suite_from: int|null}
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['yacht', 'itinerary']);

        $year = (int) $this->date->format('Y');

        return [
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
        ];
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
