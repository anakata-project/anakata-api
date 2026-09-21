<?php

declare(strict_types=1);

namespace App\Support\Guests;

use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

final class Age
{
    /**
     * Whole years on a calendar date — the prototype's `ageAt`.
     * Never an instant difference.
     */
    public static function at(?CarbonImmutable $dob, CarbonImmutable $on): ?int
    {
        if ($dob === null) {
            return null;
        }

        $age = $on->year - $dob->year;
        $month = $on->month - $dob->month;

        if ($month < 0 || ($month === 0 && $on->day < $dob->day)) {
            $age--;
        }

        return $age;
    }

    public static function isMinorNow(?CarbonImmutable $dob, ?CarbonImmutable $today = null): bool
    {
        $today ??= BusinessTime::now();
        $age = self::at($dob, $today);

        return $age !== null && $age < 18;
    }
}
