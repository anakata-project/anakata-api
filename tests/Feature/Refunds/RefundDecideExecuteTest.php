<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Booking;
use App\Models\ChangeHistory;
use App\Models\Payment;
use App\Support\Payments\Ledger;
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

/**
 * @return array{0: int, 1: int}
 */
function queuedRefund(): array
{
    test()->travelTo(CarbonImmutable::parse('2026-07-11 12:00:00', 'Pacific/Galapagos'));
    $booking = refundCabin([
        'departure' => ReservationFixtures::anamaraDeparture('2027-11-07'),
        'status' => BookingStatus::Confirmed,
        'paid' => 2660,
        'reference' => 'ANK-2026-0523',
        'cabin_code' => 'S3',
    ]);

    test()->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/transition', [
            'to' => 'CANCELLED',
            'reason' => 'Guest withdrew',
        ])
        ->assertOk();

    $refundId = $booking->fresh()?->refundRequest?->id;
    expect($refundId)->toBeInt();

    return [$booking->id, $refundId];
}

test('approve then execute writes a negative refunded row and leaves the booking cancelled', function (): void {
    [$bookingId, $refundId] = queuedRefund();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/decide', [
            'decision' => RefundRequestStatus::Approved->value,
            'reason' => 'Band is correct',
        ])
        ->assertOk()
        ->assertJsonPath('status', RefundRequestStatus::Approved->value);

    $this->actingAs(externalFinanceUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/execute', [
            'method' => PaymentMethod::Wire->value,
        ])
        ->assertOk()
        ->assertJsonPath('status', RefundRequestStatus::Executed->value);

    $booking = Booking::query()->findOrFail($bookingId);
    $payment = Payment::query()->where('booking_id', $bookingId)->where('kind', PaymentKind::Refund)->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Cancelled);
    expect($payment->amount)->toBe(-1330);
    expect($payment->status)->toBe(PaymentStatus::Refunded);
    expect($payment->reference)->toEndWith('-R01');
    expect(Ledger::paid($booking))->toBe(1330);

    $history = ChangeHistory::query()
        ->where('subject_id', $bookingId)
        ->where('event', 'refund.executed')
        ->latest('id')
        ->firstOrFail();
    expect($history->after['what'] ?? '')->toBe('Refund executed — USD 1,330 (5 % penalty band)');
    expect($history->reason)->toBe('Director approval');
});

test('executing an unapproved request a second time or with the wrong amount is 422', function (): void {
    [$bookingId, $refundId] = queuedRefund();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/execute', [
            'method' => PaymentMethod::Wire->value,
        ])
        ->assertUnprocessable();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/decide', [
            'decision' => RefundRequestStatus::Approved->value,
            'reason' => 'Band is correct',
        ])
        ->assertOk();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/execute', [
            'method' => PaymentMethod::Wire->value,
            'amount' => 500,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/execute', [
            'method' => PaymentMethod::Wire->value,
            'amount' => 1330,
        ])
        ->assertOk();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/execute', [
            'method' => PaymentMethod::Wire->value,
        ])
        ->assertUnprocessable();

    expect(Payment::query()->where('booking_id', $bookingId)->where('kind', PaymentKind::Refund)->count())->toBe(1);
    expect(Booking::query()->findOrFail($bookingId)->status)->toBe(BookingStatus::Cancelled);
});

test('reason is required to approve or reject', function (): void {
    [, $refundId] = queuedRefund();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/decide', [
            'decision' => RefundRequestStatus::Approved->value,
            'reason' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});
