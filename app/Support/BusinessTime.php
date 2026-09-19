<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class BusinessTime
{
    public static function zone(): string
    {
        return (string) config('anakata.business_timezone');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone());
    }

    public static function year(CarbonInterface $at): int
    {
        return self::toBusiness($at)->year;
    }

    public static function toBusiness(CarbonInterface $utc): CarbonImmutable
    {
        return CarbonImmutable::instance($utc)->setTimezone(self::zone());
    }
}
