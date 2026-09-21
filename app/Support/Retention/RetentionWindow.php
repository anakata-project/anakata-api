<?php

declare(strict_types=1);

namespace App\Support\Retention;

use Carbon\CarbonImmutable;

final class RetentionWindow
{
    public static function passportEndsOn(CarbonImmutable $returnDate, int $months): CarbonImmutable
    {
        return $returnDate->addMonthsNoOverflow($months);
    }

    public static function notesEndOn(CarbonImmutable $returnDate, int $days): CarbonImmutable
    {
        return $returnDate->addDays($days);
    }

    public static function elapsed(CarbonImmutable $endOn, string $today): bool
    {
        return $today > $endOn->toDateString();
    }
}
