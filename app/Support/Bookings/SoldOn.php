<?php

declare(strict_types=1);

namespace App\Support\Bookings;

use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class SoldOn
{
    public static function fromTimestamp(CarbonInterface $at): string
    {
        return BusinessTime::toBusiness($at)->toDateString();
    }

    public static function today(): string
    {
        return BusinessTime::now()->toDateString();
    }

    /**
     * Fill null sold_on from created_at in the Galápagos calendar.
     */
    public static function backfillMissing(): int
    {
        $updated = 0;

        DB::table('bookings')
            ->whereNull('sold_on')
            ->orderBy('id')
            ->each(function (object $row) use (&$updated): void {
                $created = $row->created_at;

                if (! is_string($created) && ! $created instanceof CarbonInterface) {
                    return;
                }

                $at = $created instanceof CarbonInterface
                    ? $created
                    : CarbonImmutable::parse($created, 'UTC');

                DB::table('bookings')->where('id', $row->id)->update([
                    'sold_on' => self::fromTimestamp($at),
                ]);
                $updated++;
            });

        return $updated;
    }
}
