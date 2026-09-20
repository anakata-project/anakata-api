<?php

declare(strict_types=1);

namespace App\Actions\Itineraries;

use App\Actions\Action;
use App\Enums\ItineraryStatus;
use App\Models\Itinerary;
use App\Support\History\History;
use App\Support\Itineraries\Completeness;
use Illuminate\Validation\ValidationException;

final class UpdateItinerary extends Action
{
    /** @var list<string> */
    private const HISTORY_EXCLUDED = [
        'updated_at',
        'updated_by',
        'created_at',
        'created_by',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Itinerary $itinerary, array $data): Itinerary
    {
        unset($data['code'], $data['hero_image_path']);

        return $this->transaction(function () use ($itinerary, $data): Itinerary {
            foreach ($data as $key => $value) {
                $itinerary->setAttribute($key, $value);
            }

            if ($itinerary->status === ItineraryStatus::Published) {
                $completeness = Completeness::for($itinerary);

                if ($completeness->blocking !== []) {
                    throw ValidationException::withMessages([
                        'status' => ['Cannot publish — missing: '.implode(', ', $completeness->blocking).'.'],
                    ]);
                }
            }

            if (! $itinerary->isDirty()) {
                return $itinerary;
            }

            $itinerary->save();

            $changes = $itinerary->getChanges();
            $previous = $itinerary->getPrevious();

            $contentBefore = [];
            $contentAfter = [];

            foreach ($changes as $key => $value) {
                if ($key === 'status' || in_array($key, self::HISTORY_EXCLUDED, true)) {
                    continue;
                }

                $contentBefore[$key] = $previous[$key] ?? null;
                $contentAfter[$key] = $value;
            }

            if ($contentBefore !== []) {
                History::record($itinerary, 'itinerary.updated', $contentBefore, $contentAfter);
            }

            if (array_key_exists('status', $changes)) {
                $newStatus = $itinerary->status;
                $event = match ($newStatus) {
                    ItineraryStatus::Published => 'itinerary.published',
                    ItineraryStatus::Hidden => 'itinerary.hidden',
                    ItineraryStatus::Draft => 'itinerary.updated',
                };

                History::record(
                    $itinerary,
                    $event,
                    ['status' => $previous['status'] ?? null],
                    ['status' => $changes['status']],
                );
            }

            return $itinerary;
        });
    }
}
