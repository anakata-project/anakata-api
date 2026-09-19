<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ConfigPublished;
use App\Services\Config\CurrentConfig;
use Illuminate\Support\Facades\Cache;

final class ClearCurrentConfigCache
{
    public function handle(ConfigPublished $event): void
    {
        Cache::forget($event->kind->cacheKey());
        app(CurrentConfig::class)->forget($event->kind);
    }
}
