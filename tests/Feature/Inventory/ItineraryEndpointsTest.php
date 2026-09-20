<?php

declare(strict_types=1);

use App\Enums\ItineraryStatus;
use App\Models\ChangeHistory;
use App\Models\Itinerary;
use App\Support\Itineraries\Defaults;
use App\Support\Itineraries\Gradients;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

test('lucia can view itineraries and cannot write', function (): void {
    $itinerary = Itinerary::factory()->create(['code' => 'WEST']);
    $lucia = salesExecUser();

    $this->actingAs($lucia)
        ->getJson('/api/rms/itineraries')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'WEST');

    $this->actingAs($lucia)
        ->getJson("/api/rms/itineraries/{$itinerary->id}")
        ->assertOk();

    $this->actingAs($lucia)
        ->getJson('/api/rms/itineraries/defaults')
        ->assertOk();

    $this->actingAs($lucia)
        ->postJson('/api/rms/itineraries', ['code' => 'SOUTH', 'name' => 'South'])
        ->assertForbidden();

    $this->actingAs($lucia)
        ->patchJson("/api/rms/itineraries/{$itinerary->id}", ['name' => 'Renamed'])
        ->assertForbidden();

    $this->actingAs($lucia)
        ->deleteJson("/api/rms/itineraries/{$itinerary->id}")
        ->assertForbidden();
});

test('mateo can create a draft itinerary with mkItin defaults', function (): void {
    $mateo = managerUser();

    $response = $this->actingAs($mateo)
        ->postJson('/api/rms/itineraries', [
            'code' => 'south',
            'name' => 'Southern Isles',
        ]);

    $response->assertCreated()
        ->assertJsonPath('code', 'SOUTH')
        ->assertJsonPath('status', 'DRAFT')
        ->assertJsonPath('days', 8)
        ->assertJsonPath('nights', 7)
        ->assertJsonPath('embark', 'San Cristóbal (SCY)')
        ->assertJsonPath('fallback_gradient', Gradients::css(Gradients::DEFAULT_KEY))
        ->assertJsonPath('fallback_gradient_key', Gradients::DEFAULT_KEY)
        ->assertJsonPath('chips.0', Defaults::CHIPS[0]);

    expect(Itinerary::query()->where('code', 'SOUTH')->firstOrFail()->status)->toBe(ItineraryStatus::Draft);

    $entry = ChangeHistory::query()->where('event', 'itinerary.created')->first();
    expect($entry)->not->toBeNull();
    expect($entry?->subject_type)->toBe('itinerary');
});

test('a panel-shaped create with empty draft strings succeeds', function (): void {
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->postJson('/api/rms/itineraries', [
            'code' => 'SOUTH',
            'name' => 'Southern Isles',
            'hero_alt' => '',
            'card_description' => '',
            'long_description' => '',
            'meta_title' => '',
            'meta_description' => '',
            'slug' => '',
            'fallback_gradient' => Gradients::DEFAULT_KEY,
            'highlights' => [],
            'day_plan' => [],
        ])
        ->assertCreated()
        ->assertJsonPath('code', 'SOUTH')
        ->assertJsonPath('status', 'DRAFT')
        ->assertJsonPath('hero_alt', '')
        ->assertJsonPath('card_description', '')
        ->assertJsonPath('slug', null);
});

test('defaults match mkItin', function (): void {
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->getJson('/api/rms/itineraries/defaults')
        ->assertOk()
        ->assertJsonPath('status', 'DRAFT')
        ->assertJsonPath('days', 8)
        ->assertJsonPath('nights', 7)
        ->assertJsonPath('sort_order', 9)
        ->assertJsonPath('embark', 'San Cristóbal (SCY)')
        ->assertJsonPath('facts.0.0', 'Accommodation')
        ->assertJsonPath('fallback_gradient', Gradients::ALL[Gradients::DEFAULT_KEY])
        ->assertJsonPath('fallback_gradient_key', Gradients::DEFAULT_KEY)
        ->assertJsonPath('gradients', Gradients::catalog());
});

test('itinerary rows expose fallback_gradient_key alongside the css', function (): void {
    $itinerary = Itinerary::factory()->create([
        'code' => 'NORTH',
        'fallback_gradient' => 'Northern (forest)',
    ]);
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->getJson("/api/rms/itineraries/{$itinerary->id}")
        ->assertOk()
        ->assertJsonPath('fallback_gradient', Gradients::css('Northern (forest)'))
        ->assertJsonPath('fallback_gradient_key', 'Northern (forest)');

    $this->actingAs($mateo)
        ->getJson('/api/rms/itineraries')
        ->assertOk()
        ->assertJsonPath('data.0.fallback_gradient_key', 'Northern (forest)')
        ->assertJsonPath('data.0.fallback_gradient', Gradients::css('Northern (forest)'));
});

test('code is immutable after creation', function (): void {
    $itinerary = Itinerary::factory()->create(['code' => 'WEST']);
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->patchJson("/api/rms/itineraries/{$itinerary->id}", ['code' => 'EAST'])
        ->assertOk()
        ->assertJsonPath('code', 'WEST');

    expect($itinerary->fresh()?->code)->toBe('WEST');
});

test('publishing without a day plan returns 422 naming day-by-day plan', function (): void {
    $itinerary = Itinerary::factory()->create([
        'name' => 'Western Realm',
        'card_description' => 'A card description.',
        'days' => 8,
        'nights' => 7,
        'day_plan' => [],
    ]);
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->patchJson("/api/rms/itineraries/{$itinerary->id}", ['status' => 'PUBLISHED'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect($this->actingAs($mateo)->patchJson("/api/rms/itineraries/{$itinerary->id}", ['status' => 'PUBLISHED'])->json('message'))
        ->toContain('day-by-day plan');

    expect($itinerary->fresh()?->status)->toBe(ItineraryStatus::Draft);
});

test('publishing a complete itinerary succeeds', function (): void {
    $itinerary = Itinerary::factory()->publishable()->create();
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->patchJson("/api/rms/itineraries/{$itinerary->id}", ['status' => 'PUBLISHED'])
        ->assertOk()
        ->assertJsonPath('status', 'PUBLISHED');
});

test('an itinerary can be deleted while no departures exist', function (): void {
    $itinerary = Itinerary::factory()->create();
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->deleteJson("/api/rms/itineraries/{$itinerary->id}")
        ->assertNoContent();

    expect(Itinerary::query()->count())->toBe(0);
    expect(ChangeHistory::query()->where('event', 'itinerary.deleted')->count())->toBe(1);
});

test('code validation follows the prototype editor', function (): void {
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->postJson('/api/rms/itineraries', ['code' => 'S'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    $this->actingAs($mateo)
        ->postJson('/api/rms/itineraries', ['code' => 'SOUTH-1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

test('completeness lists missing fields in prototype order', function (): void {
    $itinerary = Itinerary::factory()->create([
        'name' => '',
        'card_description' => '',
        'day_plan' => [],
        'highlights' => [],
        'long_description' => '',
        'included' => [],
        'excluded' => [],
        'faqs' => [],
        'slug' => null,
        'meta_title' => '',
        'meta_description' => '',
        'hero_image_path' => null,
    ]);
    $mateo = managerUser();

    $missing = $this->actingAs($mateo)
        ->getJson("/api/rms/itineraries/{$itinerary->id}")
        ->assertOk()
        ->json('completeness.missing');

    expect($missing)->toBe([
        'name',
        'card description',
        'day-by-day plan',
        'hero photo',
        'highlights',
        'long description',
        'includes',
        'excludes',
        'FAQs',
        'URL slug',
        'SEO title',
        'SEO description',
    ]);
    expect($this->actingAs($mateo)->getJson("/api/rms/itineraries/{$itinerary->id}")->json('completeness.blocking'))
        ->toBe(['name', 'card description', 'day-by-day plan']);
    expect($this->actingAs($mateo)->getJson("/api/rms/itineraries/{$itinerary->id}")->json('completeness.pct'))
        ->toBe((int) round((13 - 12) / 13 * 100));
});
