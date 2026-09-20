<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\CommissionAccrualStatus;
use App\Enums\MainChannel;
use App\Enums\Permission;
use App\Models\Agency;
use App\Models\Booking;
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

test('the accrual list derives accrued blocked payable and cancelled', function (): void {
    $agency = Agency::factory()->create(['commission_pct' => 10]);
    $departure = ReservationFixtures::anamaraDeparture('2027-11-07');
    $past = ReservationFixtures::anamaraDeparture('2026-06-07');

    $accruedId = test()->actingAs(managerUser())
        ->postJson('/api/rms/bookings', ReservationFixtures::createPayload($departure, [
            'cabins' => [['cabin_code' => 'S1', 'adults' => 2, 'children' => 0]],
            'main_channel' => MainChannel::B2BTravelAdvisor->value,
            'channel_of_origin' => 'Travel Advisor',
            'agency_id' => $agency->id,
            'commission_pct' => 10,
        ]))
        ->assertCreated()
        ->json('bookings.0.id');

    $blockedId = test()->actingAs(managerUser())
        ->postJson('/api/rms/bookings', ReservationFixtures::createPayload($departure, [
            'cabins' => [['cabin_code' => 'S2', 'adults' => 2, 'children' => 0]],
            'main_channel' => MainChannel::B2BTravelAdvisor->value,
            'channel_of_origin' => 'Travel Advisor',
            'agency_id' => $agency->id,
            'commission_pct' => 15,
        ]))
        ->assertCreated()
        ->json('bookings.0.id');

    $cancelled = Booking::factory()->create([
        'departure_id' => $departure->id,
        'cabin_id' => $departure->yacht->cabins->firstWhere('code', 'S3')?->id,
        'agency_id' => $agency->id,
        'commission_pct' => 10,
        'commission_approved' => true,
        'status' => BookingStatus::Cancelled,
        'main_channel' => MainChannel::B2BTravelAdvisor,
    ]);

    $payable = Booking::factory()->create([
        'departure_id' => $past->id,
        'cabin_id' => $past->yacht->cabins->firstWhere('code', 'S1')?->id,
        'agency_id' => $agency->id,
        'commission_pct' => 10,
        'commission_approved' => true,
        'status' => BookingStatus::Completed,
        'main_channel' => MainChannel::B2BTravelAdvisor,
    ]);

    $rows = $this->actingAs(adminUser())
        ->getJson('/api/rms/commissions')
        ->assertOk()
        ->json('data');

    $byId = collect($rows)->keyBy('booking_id');
    expect($byId[$accruedId]['status'])->toBe(CommissionAccrualStatus::Accrued->value);
    expect($byId[$blockedId]['status'])->toBe(CommissionAccrualStatus::Blocked->value);
    expect($byId[$cancelled->id]['status'])->toBe(CommissionAccrualStatus::Cancelled->value);
    expect($byId[$payable->id]['status'])->toBe(CommissionAccrualStatus::Payable->value);

    $this->actingAs(adminUser())
        ->getJson('/api/rms/commissions?status='.CommissionAccrualStatus::Blocked->value)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.booking_id', $blockedId);

    $role = Role::factory()->create([
        'permissions' => [Permission::PanelRms],
    ]);
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson('/api/rms/commissions')
        ->assertForbidden();
});
