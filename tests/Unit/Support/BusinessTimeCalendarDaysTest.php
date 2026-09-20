<?php

declare(strict_types=1);

use App\Support\BusinessTime;

test('calendarDaysBetween is a date difference not a UTC instant', function (): void {
    expect(BusinessTime::calendarDaysBetween('2026-07-10', '2027-11-06'))->toBe(484);
    expect(BusinessTime::calendarDaysBetween('2027-11-07', '2027-11-07'))->toBe(0);
    expect(BusinessTime::calendarDaysBetween('2027-08-10', '2027-11-07'))->toBe(89);
});
