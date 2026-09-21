<?php

declare(strict_types=1);

use App\Enums\PngCategory as Category;
use App\Support\Config\Documents\EngineSettingsDocument;
use App\Support\Guests\PngCategory;
use Carbon\CarbonImmutable;

function pngSettings(): EngineSettingsDocument
{
    return EngineSettingsDocument::fromArray(EngineSettingsDocument::initial());
}

function pngDate(string $ymd): CarbonImmutable
{
    return CarbonImmutable::createFromFormat('!Y-m-d', $ymd);
}

test('missing date of birth or nationality is pending with a null fee', function (): void {
    $settings = pngSettings();
    $departure = pngDate('2027-03-07');

    expect(PngCategory::for(null, 'US', false, $departure, $settings))->toBe([
        'category' => Category::Pending,
        'fee' => null,
    ]);
    expect(PngCategory::for(pngDate('1990-01-01'), null, false, $departure, $settings))->toBe([
        'category' => Category::Pending,
        'fee' => null,
    ]);
    expect(PngCategory::for(pngDate('1990-01-01'), '', true, $departure, $settings))->toBe([
        'category' => Category::Pending,
        'fee' => null,
    ]);
});

test('under the published exempt age is exempt with fee 0', function (): void {
    $settings = pngSettings();
    $departure = pngDate('2027-03-07');

    expect(PngCategory::for(pngDate('2025-06-01'), 'US', false, $departure, $settings))->toBe([
        'category' => Category::Exempt,
        'fee' => 0,
    ]);
    expect(PngCategory::for(pngDate('2025-03-08'), 'US', false, $departure, $settings))->toBe([
        'category' => Category::Exempt,
        'fee' => 0,
    ]);
    expect(PngCategory::for(pngDate('2025-03-07'), 'US', false, $departure, $settings))->toBe([
        'category' => Category::Foreign12AndUnder,
        'fee' => 100,
    ]);
});

test('an ecuadorian or resident pays the national rate at any age over the exempt cutoff', function (): void {
    $settings = pngSettings();
    $departure = pngDate('2027-03-07');

    expect(PngCategory::for(pngDate('2016-01-01'), 'EC', false, $departure, $settings))->toBe([
        'category' => Category::NationalOrResident,
        'fee' => 30,
    ]);
    expect(PngCategory::for(pngDate('1980-01-01'), 'EC', false, $departure, $settings))->toBe([
        'category' => Category::NationalOrResident,
        'fee' => 30,
    ]);
    expect(PngCategory::for(pngDate('1980-01-01'), 'US', true, $departure, $settings))->toBe([
        'category' => Category::NationalOrResident,
        'fee' => 30,
    ]);
});

test('andean community countries split at 12', function (string $code): void {
    $settings = pngSettings();
    $departure = pngDate('2027-03-07');

    expect(PngCategory::for(pngDate('2014-03-08'), $code, false, $departure, $settings))->toBe([
        'category' => Category::CanMinor,
        'fee' => 30,
    ]);
    expect(PngCategory::for(pngDate('2014-03-07'), $code, false, $departure, $settings))->toBe([
        'category' => Category::CanAdult,
        'fee' => 100,
    ]);
})->with(['CO', 'PE', 'BO']);

test('other foreigners split at 12 using published amounts', function (): void {
    $settings = pngSettings();
    $departure = pngDate('2027-03-07');

    expect(PngCategory::for(pngDate('2014-03-08'), 'US', false, $departure, $settings))->toBe([
        'category' => Category::Foreign12AndUnder,
        'fee' => 100,
    ]);
    expect(PngCategory::for(pngDate('2014-03-07'), 'US', false, $departure, $settings))->toBe([
        'category' => Category::ForeignOver12,
        'fee' => 200,
    ]);
});

test('an ecuadorian infant is exempt before the national rate', function (): void {
    $settings = pngSettings();

    expect(PngCategory::for(pngDate('2026-01-01'), 'EC', false, pngDate('2027-03-07'), $settings))->toBe([
        'category' => Category::Exempt,
        'fee' => 0,
    ]);
});
