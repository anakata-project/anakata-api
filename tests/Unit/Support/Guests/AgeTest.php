<?php

declare(strict_types=1);

use App\Support\Guests\Age;
use Carbon\CarbonImmutable;

test('age at a calendar date is whole years and does not use an instant difference', function (): void {
    $dob = CarbonImmutable::createFromFormat('!Y-m-d', '2015-03-02');

    expect(Age::at($dob, CarbonImmutable::createFromFormat('!Y-m-d', '2027-03-01')))->toBe(11);
    expect(Age::at($dob, CarbonImmutable::createFromFormat('!Y-m-d', '2027-03-02')))->toBe(12);
    expect(Age::at($dob, CarbonImmutable::createFromFormat('!Y-m-d', '2027-03-03')))->toBe(12);
});

test('a missing date of birth has no age', function (): void {
    expect(Age::at(null, CarbonImmutable::createFromFormat('!Y-m-d', '2027-03-02')))->toBeNull();
});

test('is minor now is under 18 on the given calendar date', function (): void {
    $today = CarbonImmutable::createFromFormat('!Y-m-d', '2026-09-21');

    expect(Age::isMinorNow(CarbonImmutable::createFromFormat('!Y-m-d', '2008-09-22'), $today))->toBeTrue();
    expect(Age::isMinorNow(CarbonImmutable::createFromFormat('!Y-m-d', '2008-09-21'), $today))->toBeFalse();
    expect(Age::isMinorNow(null, $today))->toBeFalse();
});
