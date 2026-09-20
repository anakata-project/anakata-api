<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('users without view_all only see their own bookings', function (): void {
    $departure = ReservationFixtures::anamaraDeparture();
    $role = Role::factory()->create([
        'permissions' => [Permission::PanelRms, Permission::BookingsCreate],
    ]);
    $owner = User::factory()->create(['role_id' => $role->id]);
    $other = User::factory()->create(['role_id' => $role->id]);

    $mine = Booking::factory()->create([
        'departure_id' => $departure->id,
        'cabin_id' => $departure->yacht->cabins->firstWhere('code', 'S1')?->id,
        'owner_id' => $owner->id,
    ]);
    Booking::factory()->create([
        'departure_id' => $departure->id,
        'cabin_id' => $departure->yacht->cabins->firstWhere('code', 'S2')?->id,
        'owner_id' => $other->id,
    ]);

    $this->actingAs($owner)
        ->getJson('/api/rms/bookings')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);

    $this->actingAs($other)
        ->getJson('/api/rms/bookings/'.$mine->id)
        ->assertForbidden();
});

test('can_act follows the own-records rule', function (): void {
    $departure = ReservationFixtures::anamaraDeparture();
    $owner = salesExecUser();
    $other = salesExecUser();
    $booking = Booking::factory()->create([
        'departure_id' => $departure->id,
        'cabin_id' => $departure->yacht->cabins->firstWhere('code', 'S1')?->id,
        'owner_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->getJson('/api/rms/bookings/'.$booking->id)
        ->assertOk()
        ->assertJsonPath('can_act', true);

    $this->actingAs($other)
        ->getJson('/api/rms/bookings/'.$booking->id)
        ->assertOk()
        ->assertJsonPath('can_act', false);

    $this->actingAs(adminUser())
        ->getJson('/api/rms/bookings/'.$booking->id)
        ->assertOk()
        ->assertJsonPath('can_act', true)
        ->assertJsonPath('allowed_transitions.0.to', 'CONFIRMED')
        ->assertJsonPath('allowed_transitions.0.reason_required', false)
        ->assertJsonPath('allowed_transitions.1.to', 'CANCELLED')
        ->assertJsonPath('allowed_transitions.1.reason_required', true)
        ->assertJsonPath('balance', $booking->total);
});

test('contacts search returns the top 10 matches', function (): void {
    Contact::factory()->create(['name' => 'Harrison Whitfield', 'email' => 'd.harrison@example.test']);
    Contact::factory()->create(['name' => 'Other', 'email' => 'other@example.test']);

    $this->actingAs(managerUser())
        ->getJson('/api/rms/contacts?q=harrison')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs(managerUser())
        ->getJson('/api/rms/contacts')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('groups are scoped like bookings.view_all', function (): void {
    $departure = ReservationFixtures::anamaraDeparture();
    $role = Role::factory()->create([
        'permissions' => [Permission::PanelRms, Permission::BookingsCreate],
    ]);
    $owner = User::factory()->create(['role_id' => $role->id]);
    $other = User::factory()->create(['role_id' => $role->id]);
    $group = Group::factory()->create(['departure_id' => $departure->id]);
    Booking::factory()->create([
        'departure_id' => $departure->id,
        'group_id' => $group->id,
        'owner_id' => $owner->id,
        'cabin_id' => $departure->yacht->cabins->firstWhere('code', 'S1')?->id,
    ]);

    $this->actingAs($owner)
        ->getJson('/api/rms/groups?departure_id='.$departure->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($other)
        ->getJson('/api/rms/groups?departure_id='.$departure->id)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
