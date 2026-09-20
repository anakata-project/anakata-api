<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\ChangeHistory;
use App\Services\Inventory\Availability;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('a manager cannot delete and an admin can with a reason', function (): void {
    $departure = ReservationFixtures::anamaraDeparture();
    $id = $this->actingAs(managerUser())
        ->postJson('/api/rms/bookings', ReservationFixtures::createPayload($departure))
        ->assertCreated()
        ->json('bookings.0.id');

    $this->actingAs(managerUser())
        ->deleteJson('/api/rms/bookings/'.$id, ['reason' => 'Cleanup'])
        ->assertForbidden();

    $this->actingAs(adminUser())
        ->deleteJson('/api/rms/bookings/'.$id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $this->actingAs(adminUser())
        ->deleteJson('/api/rms/bookings/'.$id, ['reason' => 'Duplicate entry'])
        ->assertNoContent();

    expect(Booking::query()->find($id))->toBeNull();
    expect(Booking::withTrashed()->find($id)?->deleted_at)->not->toBeNull();

    $this->actingAs(adminUser())
        ->getJson('/api/rms/bookings/'.$id)
        ->assertNotFound();

    $this->actingAs(adminUser())
        ->getJson('/api/rms/bookings')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $snapshot = app(Availability::class)->forDepartures(collect([$departure]))[$departure->id];
    expect(collect($snapshot->cabins)->firstWhere('cabin.code', 'S1')['state'])->toBe('FREE');

    $history = ChangeHistory::query()->where('event', 'booking.deleted')->firstOrFail();
    expect($history->reason)->toBe('Duplicate entry');
    expect($history->after['what'] ?? null)->toBe('Reservation deleted');
});
