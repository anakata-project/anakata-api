<?php

declare(strict_types=1);

use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

test('the application timezone stays UTC', function (): void {
    expect(config('app.timezone'))->toBe('UTC');
});

test('the business zone is Pacific/Galapagos', function (): void {
    expect(BusinessTime::zone())->toBe('Pacific/Galapagos');
});

test('now is in the business zone', function (): void {
    expect(BusinessTime::now()->timezoneName)->toBe('Pacific/Galapagos');
});

test('the Galapagos year boundary is 2026 while UTC is already 2027', function (): void {
    $at = CarbonImmutable::parse('2026-12-31 23:30:00', 'Pacific/Galapagos');

    expect(BusinessTime::year($at))->toBe(2026);
    expect($at->utc()->year)->toBe(2027);
    expect(BusinessTime::toBusiness($at->utc())->year)->toBe(2026);
});
