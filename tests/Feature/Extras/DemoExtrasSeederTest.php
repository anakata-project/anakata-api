<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\BookingExtra;
use App\Services\Config\CurrentConfig;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\DemoBookingsSeeder;
use Database\Seeders\DemoExtrasSeeder;
use Database\Seeders\DemoGuestsSeeder;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoRequestsSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(DemoUsersSeeder::class);
    $this->seed(DemoInventorySeeder::class);
    $this->seed(DemoBookingsSeeder::class);
    $this->seed(DemoRequestsSeeder::class);
    $this->seed(DemoGuestsSeeder::class);
});

test('demo extras and collected fees attach to 0007 0009 and 0011 and a second seed is a no-op', function (): void {
    $this->seed(DemoExtrasSeeder::class);
    $this->seed(DemoExtrasSeeder::class);

    $catalogue = app(CurrentConfig::class)->extras();
    $flt = $catalogue->find('FLT');
    $hpre = $catalogue->find('HPRE');
    $png = app(CurrentConfig::class)->engineSettings()->fees->png->foreignOver12;

    expect($flt?->priceUsd)->not->toBeNull();
    expect($hpre?->priceUsd)->not->toBeNull();

    $okafor = Booking::query()->where('reference', 'ANK-2026-0011')->firstOrFail();
    expect($okafor->extrasTotal())->toBe((int) $flt?->priceUsd * 2);
    expect(BookingExtra::query()->where('booking_id', $okafor->id)->where('code', 'FLT')->count())->toBe(1);

    $castellanos = Booking::query()->where('reference', 'ANK-2026-0007')->firstOrFail();
    expect($castellanos->extrasTotal())->toBe((int) $hpre?->priceUsd);
    expect($castellanos->depositAmount())->toBe(2328);

    $soderberg = Booking::query()->where('reference', 'ANK-2026-0009')->firstOrFail();
    expect($soderberg->png_collected)->toBeTrue();
    expect($soderberg->feesCollectedTotal())->toBe($png * $soderberg->guests()->count());
    expect($soderberg->extrasTotal())->toBe(0);

    foreach (['ANK-2026-0003', 'ANK-2026-0005'] as $reference) {
        $booking = Booking::query()->where('reference', $reference)->firstOrFail();
        expect($booking->extrasTotal())->toBe(0);
        expect($booking->png_collected)->toBeFalse();
        expect($booking->tct_collected)->toBeFalse();
        expect($booking->feesCollectedTotal())->toBe(0);
    }
});
