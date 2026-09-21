<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Enums\RefundRequestStatus;
use App\Models\Role;
use App\Models\User;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('the queue lists refunds for approve or execute and hides them from a manager', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-11 12:00:00', 'Pacific/Galapagos'));
    $booking = refundCabin([
        'departure' => ReservationFixtures::anamaraDeparture('2027-11-07'),
        'status' => BookingStatus::Confirmed,
        'paid' => 2660,
        'reference' => 'ANK-2026-0516',
        'cabin_code' => 'S8',
    ]);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/transition', [
            'to' => 'CANCELLED',
            'reason' => 'Guest withdrew',
        ])
        ->assertOk();

    $this->actingAs(adminUser())
        ->getJson('/api/rms/refunds')
        ->assertOk()
        ->assertJsonPath('data.0.refund_due', 1330)
        ->assertJsonPath('data.0.band_label', '≥120 days')
        ->assertJsonPath('data.0.can_approve', true)
        ->assertJsonPath('data.0.can_execute', true)
        ->assertJsonPath('data.0.sla_breached', false)
        ->assertJsonPath('meta.rules.refund_business_days', 15);

    $this->actingAs(externalFinanceUser())
        ->getJson('/api/rms/refunds')
        ->assertOk()
        ->assertJsonPath('data.0.can_approve', false)
        ->assertJsonPath('data.0.can_execute', true);

    $this->actingAs(managerUser())
        ->getJson('/api/rms/refunds')
        ->assertForbidden();
});

test('the queue filters by status and cancelled date', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-11 12:00:00', 'Pacific/Galapagos'));
    $first = refundCabin([
        'departure' => ReservationFixtures::anamaraDeparture('2027-11-07'),
        'status' => BookingStatus::Confirmed,
        'paid' => 2660,
        'reference' => 'ANK-2026-0517',
        'cabin_code' => 'OWNER',
    ]);
    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$first->id.'/transition', [
            'to' => 'CANCELLED',
            'reason' => 'First',
        ])
        ->assertOk();

    $this->actingAs(adminUser())
        ->getJson('/api/rms/refunds?status='.RefundRequestStatus::Pending->value.'&from=2026-07-11&to=2026-07-11')
        ->assertOk()
        ->assertJsonPath('data.0.booking.reference', 'ANK-2026-0517');

    $this->actingAs(adminUser())
        ->getJson('/api/rms/refunds?status='.RefundRequestStatus::Executed->value)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs(adminUser())
        ->getJson('/api/rms/refunds?from=2026-07-12')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a finance user cannot decide and an approve-only user cannot execute', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-11 12:00:00', 'Pacific/Galapagos'));
    $booking = refundCabin([
        'departure' => ReservationFixtures::anamaraDeparture('2027-11-14'),
        'status' => BookingStatus::Confirmed,
        'paid' => 2660,
        'reference' => 'ANK-2026-0518',
        'cabin_code' => 'S1',
    ]);
    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/transition', [
            'to' => 'CANCELLED',
            'reason' => 'Guest withdrew',
        ])
        ->assertOk();

    $refundId = $booking->fresh()?->refundRequest?->id;
    expect($refundId)->toBeInt();

    $this->actingAs(externalFinanceUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/decide', [
            'decision' => RefundRequestStatus::Approved->value,
            'reason' => 'Looks right',
        ])
        ->assertForbidden();

    $role = Role::factory()->create([
        'permissions' => [
            Permission::PanelRms,
            Permission::RefundsApprove,
        ],
    ]);
    $director = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($director)
        ->postJson('/api/rms/refunds/'.$refundId.'/execute', [
            'method' => 'CARD_STRIPE',
        ])
        ->assertForbidden();
});

test('a breached row is flagged after the sla due_by', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-13 12:00:00', 'Pacific/Galapagos'));
    $booking = refundCabin([
        'departure' => ReservationFixtures::anamaraDeparture('2027-11-21'),
        'status' => BookingStatus::Confirmed,
        'paid' => 2660,
        'reference' => 'ANK-2026-0519',
        'cabin_code' => 'S2',
    ]);
    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/transition', [
            'to' => 'CANCELLED',
            'reason' => 'Guest withdrew',
        ])
        ->assertOk();

    $dueBy = $booking->fresh()?->refundRequest?->due_by;
    expect($dueBy)->not->toBeNull();

    $this->travelTo(CarbonImmutable::instance($dueBy)->addMinute());

    $this->actingAs(adminUser())
        ->getJson('/api/rms/refunds')
        ->assertOk()
        ->assertJsonPath('data.0.sla_breached', true)
        ->assertJsonPath('data.0.business_days_remaining', 0);

    expect(BusinessTime::now()->greaterThan($dueBy))->toBeTrue();
});
