<?php

declare(strict_types=1);

namespace App\Support\Dates;

use DateTimeInterface;

final class Format
{
    /**
     * Prototype `fmtD`: "7 Nov 2027", UTC calendar date, no time zone shift.
     */
    public static function calendar(DateTimeInterface $date): string
    {
        return $date->format('j M Y');
    }
}
