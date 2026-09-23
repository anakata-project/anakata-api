<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\HoldExpired;
use App\Support\Journeys\JourneyEngine;

/**
 * Synchronous, inside the hold-release transaction. A queued listener could
 * run before that transaction commits.
 */
final class SyncJourneysOnHoldExpired
{
    public function __construct(private readonly JourneyEngine $engine) {}

    public function handle(HoldExpired $event): void
    {
        $this->engine->onHoldExpired($event);
    }
}
