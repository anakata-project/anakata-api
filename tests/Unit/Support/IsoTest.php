<?php

declare(strict_types=1);

use App\Support\Iso;
use Carbon\CarbonImmutable;

test('utc returns null for a null instant', function (): void {
    expect(Iso::utc(null))->toBeNull();
});

test('utc formats milliseconds and a Z suffix', function (): void {
    $at = CarbonImmutable::parse('2026-12-31 23:30:00.123456', 'Pacific/Galapagos');

    expect(Iso::utc($at))->toBe('2027-01-01T05:30:00.123Z');
});
