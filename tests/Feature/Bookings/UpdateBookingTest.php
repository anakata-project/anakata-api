<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\ChangeHistory;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('notes follow can_act and owner reassignment needs act_on_any', function (): void {
    $lucia = salesExecUser(['name' => 'Lucia B.']);
    $mateo = managerUser(['name' => 'Mateo R.']);
    $carolina = adminUser(['name' => 'Carolina M.']);
    $departure = ReservationFixtures::anamaraDeparture();

    $id = $this->actingAs($lucia)
        ->postJson('/api/rms/bookings', ReservationFixtures::createPayload($departure))
        ->assertCreated()
        ->json('bookings.0.id');

    $this->actingAs($lucia)
        ->patchJson('/api/rms/bookings/'.$id, ['internal_notes' => 'Call back'])
        ->assertOk()
        ->assertJsonPath('internal_notes', 'Call back');

    expect(ChangeHistory::query()->where('event', 'booking.updated')->count())->toBe(1);

    $other = $this->actingAs($mateo)
        ->postJson('/api/rms/bookings', ReservationFixtures::createPayload($departure, [
            'cabins' => [['cabin_code' => 'S2', 'adults' => 2, 'children' => 0]],
        ]))
        ->assertCreated()
        ->json('bookings.0.id');

    $this->actingAs($lucia)
        ->patchJson('/api/rms/bookings/'.$other, ['internal_notes' => 'Nope'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Blocked: own-records rule.');

    $this->actingAs($mateo)
        ->patchJson('/api/rms/bookings/'.$id, ['owner_id' => $mateo->id])
        ->assertForbidden();

    $this->actingAs($carolina)
        ->patchJson('/api/rms/bookings/'.$id, ['owner_id' => $mateo->id])
        ->assertOk()
        ->assertJsonPath('owner.id', $mateo->id);

    expect(ChangeHistory::query()->where('event', 'booking.owner_changed')->count())->toBe(1);

    $disabled = salesExecUser(['name' => 'Gone', 'status' => UserStatus::Disabled]);

    $this->actingAs($carolina)
        ->patchJson('/api/rms/bookings/'.$id, ['owner_id' => $disabled->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner_id']);

    expect(Booking::query()->findOrFail($id)->owner_id)->toBe($mateo->id);
});
