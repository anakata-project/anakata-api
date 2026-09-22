<?php

declare(strict_types=1);

namespace App\Support\Manifests;

final class ManifestReadiness
{
    public static function label(int $passengers, int $complete, bool $overdue): string
    {
        if ($passengers > 0 && $complete === $passengers) {
            return 'READY';
        }

        if ($overdue) {
            return 'OVERDUE DATA';
        }

        $pending = $passengers - $complete;

        return $pending.' PASSENGER'.($pending === 1 ? '' : 'S').' PENDING';
    }
}
