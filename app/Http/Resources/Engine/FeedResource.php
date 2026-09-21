<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     generated_at: string,
     *     itineraries: list<EngineItineraryResource>,
     *     departures: list<EngineDepartureResource>,
     *     rates: EngineRatesResource,
     *     settings: EngineSettingsResource,
     *     offers: list<EngineOfferResource>
     * }
     *
     * @phpstan-return array{
     *     generated_at: string,
     *     itineraries: list<array<string, mixed>>,
     *     departures: list<array<string, mixed>>,
     *     rates: array<string, mixed>,
     *     settings: array<string, mixed>,
     *     offers: list<array<string, mixed>>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array{
         *     generated_at: string,
         *     itineraries: list<array<string, mixed>>,
         *     departures: list<array<string, mixed>>,
         *     rates: array<string, mixed>,
         *     settings: array<string, mixed>,
         *     offers: list<array<string, mixed>>
         * } $payload
         */
        $payload = $this->resource;

        return $payload;
    }
}
