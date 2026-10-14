<?php

declare(strict_types=1);

use App\Support\Ai\CalendarPhrase;
use Carbon\CarbonImmutable;

it('resolves relative phrases in the Galápagos calendar', function (string $phrase, string $expected): void {
    $today = CarbonImmutable::parse('2026-10-14');

    expect(CalendarPhrase::resolve($phrase, $today))->toBe($expected);
})->with([
    'today' => ['today', '2026-10-14'],
    'hoy' => ['hoy', '2026-10-14'],
    'tomorrow' => ['tomorrow', '2026-10-15'],
    'manana' => ['mañana', '2026-10-15'],
    'in three days' => ['in 3 days', '2026-10-17'],
    'next sunday' => ['next sunday', '2026-10-18'],
    'iso' => ['2026-11-01', '2026-11-01'],
]);

it('returns null for a phrase that is not a date', function (): void {
    expect(CalendarPhrase::resolve('the blue one', CarbonImmutable::parse('2026-10-14')))->toBeNull();
});
