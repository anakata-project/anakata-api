<?php

declare(strict_types=1);

namespace App\Actions\Itineraries;

use App\Actions\Action;
use App\Enums\ItineraryStatus;
use App\Models\Itinerary;
use App\Services\Engine\EngineFeedVersion;
use App\Support\History\History;
use App\Support\Itineraries\Defaults;

final class CreateItinerary extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Itinerary
    {
        $itinerary = $this->transaction(function () use ($data): Itinerary {
            $payload = [
                ...Defaults::attributes(),
                ...$data,
                'status' => ItineraryStatus::Draft,
            ];

            unset($payload['hero_image_path']);

            $itinerary = Itinerary::query()->create($payload);

            History::record($itinerary, 'itinerary.created');

            return $itinerary;
        });

        EngineFeedVersion::bump();

        return $itinerary;
    }
}
