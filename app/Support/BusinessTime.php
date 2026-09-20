<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

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

    public static function calendarDay(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::zone());

        if (! $parsed instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Invalid calendar date.');
        }

        return $parsed;
    }

    public static function dayStartUtc(string $date): CarbonImmutable
    {
        return self::calendarDay($date)->startOfDay()->utc();
    }

    public static function dayEndUtc(string $date): CarbonImmutable
    {
        return self::calendarDay($date)->endOfDay()->utc();
    }
}
