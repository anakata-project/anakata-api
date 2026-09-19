<?php

declare(strict_types=1);

namespace App\Support;

final class Rounding
{
    /**
     * Half-up on positives, matching JavaScript Math.round.
     */
    public static function halfUp(float $value): int
    {
        return (int) round($value, 0, PHP_ROUND_HALF_UP);
    }
}
