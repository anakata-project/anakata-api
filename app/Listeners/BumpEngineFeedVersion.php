<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AvailabilityChanged;
use App\Events\ConfigPublished;
use App\Services\Engine\EngineFeedVersion;
use Illuminate\Support\Facades\Cache;

final class BumpEngineFeedVersion
{
    public function handle(AvailabilityChanged|ConfigPublished $event): void
    {
        EngineFeedVersion::bump();

        if ($event instanceof AvailabilityChanged) {
            foreach ($event->departureIds as $departureId) {
                Cache::forget(EngineFeedVersion::cabinsKey($departureId));
            }
        }
    }
}
