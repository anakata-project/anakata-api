<?php

declare(strict_types=1);

namespace App\Http\Resources\Portal;

use App\Models\Agency;
use App\Models\Departure;
use App\Support\Config\Documents\RatesDocument;
use App\Support\Inventory\DepartureSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Departure $departure
 * @property-read DepartureSnapshot $snapshot
 * @property-read Agency $agency
 * @property-read RatesDocument $rates
 */
class PortalAvailabilityResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     itinerary: string,
     *     yacht: string,
     *     embark: string,
     *     disembark: string,
     *     festive: bool,
     *     rate_year: int,
     *     status: string,
     *     label: array{code: string, text: string},
     *     net_rates: array{suite_pp: int, owner_pp: int}
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array{departure: Departure, snapshot: DepartureSnapshot, agency: Agency, rates: RatesDocument} $row */
        $row = $this->resource;
        $departure = $row['departure'];
        $snapshot = $row['snapshot'];
        $agency = $row['agency'];
        $rates = $row['rates'];

        $year = $rates->year((int) $departure->date->format('Y'));

        return [
            'id' => $departure->id,
            'itinerary' => $departure->itinerary->code,
            'yacht' => $departure->yacht->code,
            'embark' => $departure->date->toDateString(),
            'disembark' => $departure->returnDate()->toDateString(),
            'festive' => $departure->festive,
            'rate_year' => (int) $departure->date->format('Y'),
            'status' => $departure->status->value,
            'label' => [
                'code' => $snapshot->engineLabel['code'],
                'text' => $snapshot->engineLabel['text'],
            ],
            'net_rates' => [
                'suite_pp' => $year === null ? 0 : $agency->netOf($year->suitePp),
                'owner_pp' => $year === null ? 0 : $agency->netOf($year->ownerPp),
            ],
        ];
    }
}
