<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\ClaimKind;
use App\Enums\OverdueDecision;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\ChangeHistory;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Support\BusinessTime;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('an extension moves the due date and clears the overdue flag', function (): void {
    $booking = overdueCabin(['reference' => 'ANK-2026-0510']);
    $newDue = BusinessTime::now()->addDays(14)->toDateString();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/overdue-decision', [
            'decision' => OverdueDecision::Extend->value,
            'reason' => 'Client asked for two weeks',
            'new_due_date' => $newDue,
        ])
        ->assertOk()
        ->assertJsonPath('overdue', false)
        ->assertJsonPath('balance_due_date', $newDue)
        ->assertJsonPath('status', BookingStatus::Confirmed->value);

    $history = ChangeHistory::query()
        ->where('subject_id', $booking->id)
        ->where('event', 'booking.overdue_extended')
        ->latest('id')
        ->firstOrFail();

    expect($history->after['what'] ?? '')->toBe('OPS-007 decision — extension granted · OVERDUE → CONFIRMED');
    expect($history->reason)->toBe('Client asked for two weeks');
    expect($booking->fresh()?->balance_days)->toBe(120);
});

test('cancel per policy releases the cabin', function (): void {
    $actor = adminUser();
    $departure = ReservationFixtures::anamaraDeparture('2028-03-05');
    $created = $this->actingAs($actor)
        ->postJson('/api/rms/bookings', ReservationFixtures::createPayload($departure, [
            'cabins' => [['cabin_code' => 'S1', 'adults' => 2, 'children' => 0]],
        ]))
        ->assertCreated();

    $bookingId = $created->json('bookings.0.id');
    $booking = Booking::query()->findOrFail($bookingId);
    $booking->update([
        'status' => BookingStatus::Confirmed,
        'balance_due_date_override' => BusinessTime::now()->subDay()->toDateString(),
    ]);
    Payment::factory()->create([
        'booking_id' => $booking->id,
        'amount' => $booking->depositAmount(),
        'status' => PaymentStatus::Settled,
        'reference' => $booking->displayReference().'-D01',
    ]);

    $this->actingAs($actor)
        ->postJson('/api/rms/bookings/'.$booking->id.'/overdue-decision', [
            'decision' => OverdueDecision::Cancel->value,
            'reason' => 'No response after the due date',
        ])
        ->assertOk()
        ->assertJsonPath('status', BookingStatus::Cancelled->value);

    expect($booking->fresh()?->claims()->whereNull('released_at')->where('kind', ClaimKind::Booking)->count())->toBe(0);

    $history = ChangeHistory::query()
        ->where('subject_id', $booking->id)
        ->where('event', 'booking.status_changed')
        ->latest('id')
        ->firstOrFail();

    expect($history->after['what'] ?? '')->toBe('OPS-007 decision — cancelled per policy · OVERDUE → CANCELLED');
});

test('ops-007 refuses a missing reason and a booking that is not overdue', function (): void {
    $booking = overdueCabin(['reference' => 'ANK-2026-0511', 'cabin_code' => 'S5']);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/overdue-decision', [
            'decision' => OverdueDecision::Cancel->value,
            'reason' => '   ',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $current = pendingCabin(['reference' => 'ANK-2026-0512']);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$current->id.'/overdue-decision', [
            'decision' => OverdueDecision::Cancel->value,
            'reason' => 'Not overdue',
        ])
        ->assertUnprocessable();
});

test('own-records blocks an overdue decision on someone else\'s booking', function (): void {
    $role = Role::factory()->create([
        'permissions' => [
            Permission::PanelRms,
            Permission::BookingsOverdueDecision,
        ],
    ]);
    $mateo = User::factory()->create(['role_id' => $role->id]);
    $lucia = User::factory()->create(['role_id' => $role->id]);
    $booking = overdueCabin([
        'reference' => 'ANK-2026-0513',
        'cabin_code' => 'S6',
        'owner_id' => $mateo->id,
    ]);

    $this->actingAs($lucia)
        ->postJson('/api/rms/bookings/'.$booking->id.'/overdue-decision', [
            'decision' => OverdueDecision::Cancel->value,
            'reason' => 'Trying',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Blocked: own-records rule.');
});
