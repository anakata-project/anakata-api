<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\Support\CalendarDate\CalendarDateHost;
use Tests\Support\CalendarDate\CalendarDateHostResource;

test('a CalendarDate attribute serialises as Y-m-d regardless of app time zone', function (string $timezone): void {
    config(['app.timezone' => $timezone]);

    $host = CalendarDateHost::query()->create(['day' => '2027-11-07']);
    $host->refresh();

    expect($host->day)->toBeInstanceOf(CarbonImmutable::class);
    expect($host->day->toDateString())->toBe('2027-11-07');
    expect($host->toArray()['day'])->toBe('2027-11-07');
    expect(json_decode((string) json_encode($host), true)['day'])->toBe('2027-11-07');

    $this->app->instance('request', Request::create('/api/rms/calendar-date-hosts', 'GET'));

    expect((new CalendarDateHostResource($host))->resolve()['day'])->toBe('2027-11-07');
})->with(['UTC', 'Pacific/Galapagos', 'Asia/Tokyo']);

test('setting a CalendarDate from a DateTimeInterface stores the calendar components only', function (): void {
    config(['app.timezone' => 'Pacific/Galapagos']);

    $host = CalendarDateHost::query()->create([
        'day' => CarbonImmutable::parse('2027-11-07T23:30:00-06:00'),
    ]);
    $host->refresh();

    expect($host->toArray()['day'])->toBe('2027-11-07');
    expect($host->getRawOriginal('day'))->toBe('2027-11-07');
});
