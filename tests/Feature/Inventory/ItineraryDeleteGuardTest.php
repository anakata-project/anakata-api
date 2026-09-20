<?php

declare(strict_types=1);

use App\Models\Itinerary;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

// TODO(Sprint 3 task 02): refuse DELETE with 409 while any departure uses the itinerary.
test('delete is refused while a departure uses the itinerary', function (): void {
    $itinerary = Itinerary::factory()->create();
    $mateo = managerUser();

    $this->actingAs($mateo)
        ->deleteJson("/api/rms/itineraries/{$itinerary->id}")
        ->assertNoContent();
})->todo('Sprint 3 task 02: 409 when a departure uses the itinerary');
