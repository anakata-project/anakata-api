<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class Iso
{
    /**
     * UTC ISO-8601 with milliseconds and a Z suffix.
     *
     * @return ($at is null ? null : string)
     */
    public static function utc(?DateTimeInterface $at): ?string
    {
        if ($at === null) {
            return null;
        }

        return CarbonImmutable::instance($at)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
