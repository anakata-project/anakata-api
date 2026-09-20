<?php

declare(strict_types=1);

namespace App\Actions\Itineraries;

use App\Actions\Action;
use App\Exceptions\ConflictException;
use App\Models\Itinerary;
use App\Support\History\History;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DeleteItinerary extends Action
{
    public function handle(Itinerary $itinerary): void
    {
        $usedBy = $itinerary->departures()->count();

        if ($usedBy > 0) {
            $noun = $usedBy === 1 ? 'departure' : 'departures';

            throw new ConflictException("Used by {$usedBy} {$noun}");
        }

        $imagePath = $itinerary->hero_image_path;

        $this->transaction(function () use ($itinerary): void {
            History::record($itinerary, 'itinerary.deleted');
            $itinerary->delete();
        });

        if (is_string($imagePath) && $imagePath !== '') {
            DB::afterCommit(static function () use ($imagePath): void {
                Storage::disk('public')->delete($imagePath);
            });
        }
    }
}
