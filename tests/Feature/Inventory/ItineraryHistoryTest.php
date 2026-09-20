<?php

declare(strict_types=1);

use App\Models\ChangeHistory;
use App\Models\Itinerary;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

test('a content-only patch writes itinerary.updated', function (): void {
    $itinerary = Itinerary::factory()->create(['name' => 'Old']);
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->patchJson("/api/rms/itineraries/{$itinerary->id}", ['name' => 'New name'])
        ->assertOk();

    $events = ChangeHistory::query()
        ->where('subject_type', 'itinerary')
        ->where('subject_id', $itinerary->id)
        ->where('event', '!=', 'itinerary.created')
        ->orderBy('id')
        ->pluck('event')
        ->all();

    expect($events)->toBe(['itinerary.updated']);

    $entry = ChangeHistory::query()->where('event', 'itinerary.updated')->latest('id')->first();
    expect($entry?->before)->toHaveKey('name');
    expect($entry?->after)->toHaveKey('name');
    expect($entry?->before)->not->toHaveKey('status');
});

test('a status-only publish writes itinerary.published', function (): void {
    $itinerary = Itinerary::factory()->publishable()->create();
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->patchJson("/api/rms/itineraries/{$itinerary->id}", ['status' => 'PUBLISHED'])
        ->assertOk();

    $events = ChangeHistory::query()
        ->where('subject_type', 'itinerary')
        ->where('subject_id', $itinerary->id)
        ->whereIn('event', ['itinerary.updated', 'itinerary.published', 'itinerary.hidden'])
        ->orderBy('id')
        ->pluck('event')
        ->all();

    expect($events)->toBe(['itinerary.published']);
});

test('a status-only hide writes itinerary.hidden', function (): void {
    $itinerary = Itinerary::factory()->publishable()->create(['status' => 'PUBLISHED']);
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->patchJson("/api/rms/itineraries/{$itinerary->id}", ['status' => 'HIDDEN'])
        ->assertOk();

    $events = ChangeHistory::query()
        ->where('subject_id', $itinerary->id)
        ->whereIn('event', ['itinerary.updated', 'itinerary.published', 'itinerary.hidden'])
        ->orderBy('id')
        ->pluck('event')
        ->all();

    expect($events)->toBe(['itinerary.hidden']);
});

test('a patch that changes content and status writes two history entries', function (): void {
    $itinerary = Itinerary::factory()->publishable()->create(['name' => 'Western']);
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->patchJson("/api/rms/itineraries/{$itinerary->id}", [
            'name' => 'Western Realm',
            'status' => 'PUBLISHED',
        ])
        ->assertOk();

    $entries = ChangeHistory::query()
        ->where('subject_type', 'itinerary')
        ->where('subject_id', $itinerary->id)
        ->whereIn('event', ['itinerary.updated', 'itinerary.published'])
        ->orderBy('id')
        ->get();

    expect($entries)->toHaveCount(2);
    expect($entries[0]->event)->toBe('itinerary.updated');
    expect($entries[0]->before)->toHaveKey('name');
    expect($entries[0]->before)->not->toHaveKey('status');
    expect($entries[0]->after)->not->toHaveKey('status');
    expect($entries[1]->event)->toBe('itinerary.published');
    expect($entries[1]->before)->toHaveKey('status');
    expect($entries[1]->after['status'])->toBe('PUBLISHED');
});

test('a no-op patch writes no history', function (): void {
    $itinerary = Itinerary::factory()->create(['name' => 'Same']);
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->patchJson("/api/rms/itineraries/{$itinerary->id}", ['name' => 'Same'])
        ->assertOk();

    expect(ChangeHistory::query()->where('event', 'itinerary.updated')->count())->toBe(0);
});

test('itinerary history is listed', function (): void {
    $itinerary = Itinerary::factory()->create();
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->getJson("/api/rms/itineraries/{$itinerary->id}/history")
        ->assertOk();
});
