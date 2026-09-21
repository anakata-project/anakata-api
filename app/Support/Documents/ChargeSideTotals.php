<?php

declare(strict_types=1);

namespace App\Support\Documents;

final class ChargeSideTotals
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{vessel: int, fees_collected: int, extras: int, charges_total: int, information_total: int}
     */
    public static function fromSnapshot(array $snapshot): array
    {
        $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];

        return [
            'vessel' => (int) ($totals['vessel'] ?? 0),
            'fees_collected' => (int) ($totals['fees_collected'] ?? 0),
            'extras' => (int) ($totals['extras'] ?? 0),
            'charges_total' => (int) ($totals['charges_total'] ?? 0),
            'information_total' => (int) ($totals['information_total'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    public static function differ(array $left, array $right): bool
    {
        return self::fromSnapshot($left) !== self::fromSnapshot($right);
    }
}
