<?php

declare(strict_types=1);

use App\Support\BusinessHours;
use App\Support\Config\Documents\BusinessRulesDocument;
use App\Support\HoldExpiry;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * @param  array{business_days?: list<int>, start?: string, end?: string, holidays?: list<string>, near_term_max_days?: int}  $overrides
 */
function businessHoursCalculator(array $overrides = []): BusinessHours
{
    return new BusinessHours(
        $overrides['business_days'] ?? [1, 2, 3, 4, 5],
        $overrides['start'] ?? '09:00',
        $overrides['end'] ?? '18:00',
        $overrides['holidays'] ?? [],
        $overrides['near_term_max_days'] ?? 120,
        'Pacific/Galapagos',
    );
}

function galtDateTime(string $datetime): CarbonImmutable
{
    return CarbonImmutable::parse($datetime, 'Pacific/Galapagos');
}

function holdRulesDocument(): BusinessRulesDocument
{
    return BusinessRulesDocument::fromArray(BusinessRulesDocument::initial());
}

function calendarDepartureDate(string $ymd): CarbonImmutable
{
    $date = CarbonImmutable::createFromFormat('!Y-m-d', $ymd);

    expect($date)->toBeInstanceOf(CarbonImmutable::class);

    return $date;
}

test('Tuesday 10:00 plus 48 business hours lands the next Tuesday at 13:00', function (): void {
    // Tue 2026-09-22 10:00–18:00 = 8 h (40 left)
    // Wed–Fri = 27 h (13 left)
    // Mon 2026-09-28 = 9 h (4 left)
    // Tue 2026-09-29 09:00 + 4 h = 13:00 GALT = 19:00 UTC
    $expires = businessHoursCalculator()->addBusinessHours(galtDateTime('2026-09-22 10:00:00'), 48);

    expect($expires->toIso8601String())->toBe('2026-09-29T19:00:00+00:00');
});

test('a Friday 17:00 request carries over the weekend', function (): void {
    // Fri 2026-09-25 17:00–18:00 = 1 h (47 left)
    // Mon 28–Fri Oct 2 = 45 h (2 left)
    // Mon 2026-10-05 09:00 + 2 h = 11:00 GALT = 17:00 UTC
    $expires = businessHoursCalculator()->addBusinessHours(galtDateTime('2026-09-25 17:00:00'), 48);

    expect($expires->toIso8601String())->toBe('2026-10-05T17:00:00+00:00');
});

test('a Saturday start begins at Monday 09:00', function (): void {
    // Sat 2026-09-26 12:00 is outside a window → Mon 2026-09-28 09:00 + 1 h = 10:00 GALT
    $expires = businessHoursCalculator()->addBusinessHours(galtDateTime('2026-09-26 12:00:00'), 1);

    expect($expires->toIso8601String())->toBe('2026-09-28T16:00:00+00:00');
});

test('a holiday in the middle is skipped', function (): void {
    // Wed 2026-09-23 is a holiday.
    // Tue 10:00–18:00 = 8 h; Thu+Fri+Mon+Tue = 36 h; 4 h on Wed 2026-09-30 13:00 GALT
    $expires = businessHoursCalculator(['holidays' => ['2026-09-23']])
        ->addBusinessHours(galtDateTime('2026-09-22 10:00:00'), 48);

    expect($expires->toIso8601String())->toBe('2026-09-30T19:00:00+00:00');
});

test('long-lead from a Wednesday is the next Wednesday at 18:00', function (): void {
    // After Wed 2026-09-23: Thu, Fri, Mon, Tue, Wed 2026-09-30 18:00 GALT = 2026-10-01 00:00 UTC
    $expires = businessHoursCalculator()->endOfNthBusinessDay(galtDateTime('2026-09-23 10:00:00'), 5);

    expect($expires->toIso8601String())->toBe('2026-10-01T00:00:00+00:00');
});

test('endOfNthBusinessDay uses the Galápagos calendar day of a late-evening request', function (): void {
    // Wed 2026-09-23 23:30 GALT = Thu 2026-09-24 05:30 UTC. Galápagos day is still Wednesday.
    $fromUtc = galtDateTime('2026-09-23 23:30:00')->utc();
    expect($fromUtc->toIso8601String())->toBe('2026-09-24T05:30:00+00:00');

    $expires = businessHoursCalculator()->endOfNthBusinessDay($fromUtc, 5);

    expect($expires->toIso8601String())->toBe('2026-10-01T00:00:00+00:00');
});

test('a Saturday request counts Monday as business day 1', function (): void {
    $expires = businessHoursCalculator()->endOfNthBusinessDay(galtDateTime('2026-09-26 12:00:00'), 1);

    expect($expires->utc()->format('Y-m-d H:i:s'))->toBe('2026-09-29 00:00:00');
});

test('Monday 09:00 plus 9 hours is Monday 18:00 the same day', function (): void {
    // [09:00, 18:00) is 9 hours; landing on the exclusive end stays on Monday.
    $expires = businessHoursCalculator()->addBusinessHours(galtDateTime('2026-09-21 09:00:00'), 9);

    expect($expires->toIso8601String())->toBe('2026-09-22T00:00:00+00:00');
});

test('a start at exactly 18:00 snaps to the next opening', function (): void {
    $expires = businessHoursCalculator()->addBusinessHours(galtDateTime('2026-09-21 18:00:00'), 1);

    expect($expires->toIso8601String())->toBe('2026-09-22T16:00:00+00:00');
});

test('a start at exactly 09:00 counts from 09:00', function (): void {
    $expires = businessHoursCalculator()->addBusinessHours(galtDateTime('2026-09-21 09:00:00'), 1);

    expect($expires->toIso8601String())->toBe('2026-09-21T16:00:00+00:00');
});

test('exactly 120 calendar days is near-term and 121 is long-lead', function (): void {
    // Request Galápagos date 2026-09-22. 120 days later is 2027-01-20; 121 is 2027-01-21.
    $requested = galtDateTime('2026-09-22 10:00:00');
    $calculator = businessHoursCalculator();
    $document = holdRulesDocument();

    $near = $calculator->holdExpiry($requested, calendarDepartureDate('2027-01-20'), $document);
    expect($near->rule)->toBe(HoldExpiry::NEAR_TERM);
    expect($near->expiresAt->utc()->format('Y-m-d H:i:s'))->toBe('2026-09-29 19:00:00');

    $far = $calculator->holdExpiry($requested, calendarDepartureDate('2027-01-21'), $document);
    expect($far->rule)->toBe(HoldExpiry::LONG_LEAD);
    expect($far->expiresAt->utc()->format('Y-m-d H:i:s'))->toBe('2026-09-30 00:00:00');
});

test('a request at 23:30 Galápagos still uses that Galápagos date for the 120-day boundary', function (): void {
    // 2026-09-22 23:30 GALT = 2026-09-23 05:30 UTC. The request date is still 2026-09-22.
    $requested = galtDateTime('2026-09-22 23:30:00')->utc();
    $calculator = businessHoursCalculator();
    $document = holdRulesDocument();

    expect($calculator->holdExpiry($requested, calendarDepartureDate('2027-01-20'), $document)->rule)
        ->toBe(HoldExpiry::NEAR_TERM);
    expect($calculator->holdExpiry($requested, calendarDepartureDate('2027-01-21'), $document)->rule)
        ->toBe(HoldExpiry::LONG_LEAD);
});

test('the constructor rejects empty days, invalid times and a start that is not before the end', function (): void {
    expect(fn () => businessHoursCalculator(['business_days' => []]))->toThrow(InvalidArgumentException::class);
    expect(fn () => businessHoursCalculator(['start' => '9:00']))->toThrow(InvalidArgumentException::class);
    expect(fn () => businessHoursCalculator(['end' => '25:00']))->toThrow(InvalidArgumentException::class);
    expect(fn () => businessHoursCalculator(['start' => '18:00', 'end' => '09:00']))->toThrow(InvalidArgumentException::class);
    expect(fn () => businessHoursCalculator(['start' => '09:00', 'end' => '09:00']))->toThrow(InvalidArgumentException::class);
});

test('day-walking methods stop after 366 days', function (): void {
    $blocked = [];
    $day = CarbonImmutable::parse('2026-09-21', 'Pacific/Galapagos');

    for ($i = 0; $i < 400; $i++) {
        $blocked[] = $day->format('Y-m-d');
        $day = $day->addDay();
    }

    $calculator = businessHoursCalculator(['holidays' => $blocked]);

    expect(fn () => $calculator->addBusinessHours(galtDateTime('2026-09-21 10:00:00'), 1))
        ->toThrow(RuntimeException::class);
    expect(fn () => $calculator->endOfNthBusinessDay(galtDateTime('2026-09-21 10:00:00'), 1))
        ->toThrow(RuntimeException::class);
});
