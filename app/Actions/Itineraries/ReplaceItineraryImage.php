<?php

declare(strict_types=1);

namespace App\Actions\Itineraries;

use App\Actions\Action;
use App\Models\Itinerary;
use App\Support\History\History;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ReplaceItineraryImage extends Action
{
    public function handle(Itinerary $itinerary, UploadedFile $file): Itinerary
    {
        $newPath = $file->store('itineraries', 'public');

        if (! is_string($newPath) || $newPath === '') {
            throw new RuntimeException('The itinerary image could not be stored.');
        }

        $previousPath = $itinerary->hero_image_path;

        try {
            $this->afterFileStored($newPath);

            return $this->transaction(function () use ($itinerary, $newPath, $previousPath): Itinerary {
                $itinerary->hero_image_path = $newPath;
                $itinerary->save();

                History::record(
                    $itinerary,
                    'itinerary.image_replaced',
                    ['hero_image_path' => $previousPath],
                    ['hero_image_path' => $newPath],
                );

                if (is_string($previousPath) && $previousPath !== '') {
                    DB::afterCommit(static function () use ($previousPath): void {
                        Storage::disk('public')->delete($previousPath);
                    });
                }

                return $itinerary;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPath);

            throw $exception;
        }
    }

    protected function afterFileStored(string $path): void {}
}
