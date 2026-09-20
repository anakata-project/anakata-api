<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentLinkStatus;
use App\Models\ChangeHistory;
use App\Models\Payment;
use App\Models\PaymentLink;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('admin and external finance can create a deposit link and a sales exec cannot', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0501']);

    $this->actingAs(salesExecUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertForbidden();

    $this->actingAs(externalFinanceUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertCreated()
        ->assertJsonPath('kind', PaymentKind::Deposit->value)
        ->assertJsonPath('amount', 2660)
        ->assertJsonPath('status', PaymentLinkStatus::Open->value)
        ->assertJsonPath('mode', 'test')
        ->assertJsonPath('stripe_id', 'plink_test_001')
        ->assertJsonPath('url', 'https://buy.stripe.com/test/plink_test_001');

    expect(Payment::query()->count())->toBe(0);

    $history = ChangeHistory::query()
        ->where('subject_id', $booking->id)
        ->where('event', 'payment_link.created')
        ->firstOrFail();

    expect($history->after)->toMatchArray([
        'amount' => 2660,
        'stripe_id' => 'plink_test_001',
        'kind' => PaymentKind::Deposit->value,
    ]);
    expect($history->after)->not->toHaveKey('url');
});

test('a balance link defaults to the outstanding balance and an explicit amount is validated', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0502']);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payments', [
            'kind' => PaymentKind::Deposit->value,
            'method' => 'CARD_STRIPE',
            'amount' => 2660,
        ])
        ->assertCreated();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Balance->value,
        ])
        ->assertCreated()
        ->assertJsonPath('amount', 23940);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Balance->value,
            'amount' => 30000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kind');

    PaymentLink::query()->where('status', PaymentLinkStatus::Open)->update([
        'status' => PaymentLinkStatus::Cancelled->value,
    ]);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Balance->value,
            'amount' => 30000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');
});

test('a second open link of the same kind is refused and an open link can be cancelled', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0503']);

    $created = $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertCreated();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kind');

    $this->actingAs(adminUser())
        ->postJson('/api/rms/payment-links/'.$created->json('id').'/cancel')
        ->assertOk()
        ->assertJsonPath('status', PaymentLinkStatus::Cancelled->value);

    expect(ChangeHistory::query()->where('event', 'payment_link.cancelled')->count())->toBe(1);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/payment-links/'.$created->json('id').'/cancel')
        ->assertUnprocessable();
});

test('a cancelled booking cannot get a payment link', function (): void {
    $booking = pendingCabin(['status' => BookingStatus::Cancelled, 'reference' => 'ANK-2026-0504']);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertUnprocessable();
});

test('booking show includes payment links', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0505']);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertCreated();

    $this->actingAs(adminUser())
        ->getJson('/api/rms/bookings/'.$booking->id)
        ->assertOk()
        ->assertJsonPath('payment_links.0.stripe_id', 'plink_test_001')
        ->assertJsonPath('payment_links.0.url', 'https://buy.stripe.com/test/plink_test_001');
});
