<?php

declare(strict_types=1);

use App\Enums\ItineraryStatus;
use App\Models\Itinerary;
use App\Support\Itineraries\Gradients;
use App\Support\Itineraries\SeedMapper;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\InventorySeeder;

test('the seeded itineraries match seed-data.json via the key map', function (): void {
    $this->seed(InventorySeeder::class);
    $this->seed(DemoInventorySeeder::class);
    $this->seed(DemoInventorySeeder::class);

    $path = base_path('docs/requirements/examples/seed-data.json');
    /** @var array{itineraries: list<array<string, mixed>>} $seed */
    $seed = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    expect(Itinerary::query()->count())->toBe(3);

    foreach ($seed['itineraries'] as $row) {
        $mapped = SeedMapper::fromPrototype($row);
        $itinerary = Itinerary::query()->where('code', $mapped['code'])->firstOrFail();

        expect($mapped['code'])->toBe($row['k']);
        expect($mapped['sort_order'])->toBe($row['order']);
        expect($mapped['days'])->toBe($row['nDays']);
        expect($mapped['tagline'])->toBe($row['tag']);
        expect($mapped['fallback_gradient'])->toBe(Gradients::keyFromCss((string) $row['grad']));
        expect($mapped['hero_image_path'])->toBeNull();
        expect($mapped['hero_alt'])->toBe($row['alt']);
        expect($mapped['card_description'])->toBe($row['desc']);
        expect($mapped['long_description'])->toBe($row['long']);
        expect($mapped['highlights'])->toBe($row['hi']);
        expect($mapped['day_plan'])->toBe($row['plan']);
        expect($mapped['included'])->toBe($row['inc']);
        expect($mapped['excluded'])->toBe($row['exc']);
        expect($mapped['meta_title'])->toBe($row['metaT']);
        expect($mapped['meta_description'])->toBe($row['metaD']);

        expect($itinerary->code)->toBe($mapped['code']);
        expect($itinerary->name)->toBe($mapped['name']);
        expect($itinerary->status)->toBe(ItineraryStatus::Published);
        expect($itinerary->sort_order)->toBe($mapped['sort_order']);
        expect($itinerary->festive)->toBe($mapped['festive']);
        expect($itinerary->days)->toBe($mapped['days']);
        expect($itinerary->nights)->toBe($mapped['nights']);
        expect($itinerary->embark)->toBe($mapped['embark']);
        expect($itinerary->disembark)->toBe($mapped['disembark']);
        expect($itinerary->tagline)->toBe($mapped['tagline']);
        expect($itinerary->hero_image_path)->toBeNull();
        expect($itinerary->fallback_gradient)->toBe($mapped['fallback_gradient']);
        expect($itinerary->card_description)->toBe($mapped['card_description']);
        expect($itinerary->highlights)->toBe($mapped['highlights']);
        expect($itinerary->day_plan)->toBe($mapped['day_plan']);
        expect($itinerary->slug)->toBe($mapped['slug']);
    }

    expect(Itinerary::query()->where('status', ItineraryStatus::Published)->count())->toBe(3);
});
