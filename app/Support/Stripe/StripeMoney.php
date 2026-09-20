<?php

declare(strict_types=1);

namespace App\Support\Stripe;

final class StripeMoney
{
    public static function toCents(int $usd): int
    {
        return $usd * 100;
    }

    public static function fromCents(int $cents): int
    {
        return intdiv($cents, 100);
    }
}
