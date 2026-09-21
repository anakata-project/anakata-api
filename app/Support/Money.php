<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    public static function format(int $usd): string
    {
        return 'USD '.number_format($usd, thousands_separator: ',');
    }

    public static function formatCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);
        $dollars = intdiv($absolute, 100);
        $remainder = $absolute % 100;

        return $sign.self::format($dollars).sprintf('.%02d', $remainder);
    }

    public static function formatDocument(int $usd): string
    {
        $sign = $usd < 0 ? '−' : '';

        return $sign.number_format(abs($usd), 2, '.', ',');
    }
}
