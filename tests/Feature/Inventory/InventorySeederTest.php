<?php

declare(strict_types=1);

use App\Enums\CabinCategory;
use App\Models\Cabin;
use App\Models\Itinerary;
use App\Models\Yacht;
use Database\Seeders\InventorySeeder;

test('InventorySeeder gives two yachts with nine cabins each and is idempotent', function (): void {
    $this->seed(InventorySeeder::class);
    $this->seed(InventorySeeder::class);

    expect(Yacht::query()->count())->toBe(2);
    expect(Cabin::query()->count())->toBe(18);

    foreach (['ANAMARA', 'ANATIVA'] as $code) {
        $yacht = Yacht::query()->where('code', $code)->firstOrFail();
        expect($yacht->name)->toBe($code);
        expect($yacht->cabins)->toHaveCount(9);
        expect($yacht->cabins->pluck('code')->all())->toBe([
            'S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8', 'OWNER',
        ]);
        expect($yacht->cabins->last()?->label)->toBe("Owner's Suite");
        expect($yacht->cabins->last()?->category)->toBe(CabinCategory::Owner);
        expect($yacht->cabins->first()?->label)->toBe('Suite 01');
        expect($yacht->cabins->first()?->category)->toBe(CabinCategory::Suite);
    }
});

test('a production-like seed does not create itineraries', function (): void {
    $this->seed(InventorySeeder::class);

    expect(Itinerary::query()->count())->toBe(0);
});
