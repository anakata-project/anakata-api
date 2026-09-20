<?php

declare(strict_types=1);

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\StripeEvent;
use App\Services\Stripe\FakeStripeGateway;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

/**
 * @return array<string, mixed>
 */
function chargeRefundedEvent(string $eventId, string $refundId): array
{
    return [
        'id' => $eventId,
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_test_refund',
                'payment_intent' => 'pi_test_refund_source',
                'amount' => 266000,
                'refunds' => [
                    'data' => [
                        ['id' => $refundId, 'amount' => 266000],
                    ],
                ],
            ],
        ],
    ];
}

test('charge.refunded stamps gateway_id on the existing refund row and never changes status', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0530']);

    $settlement = Payment::factory()->create([
        'booking_id' => $booking->id,
        'kind' => PaymentKind::Deposit,
        'method' => PaymentMethod::StripeLink,
        'amount' => 2660,
        'status' => PaymentStatus::Settled,
        'gateway_id' => 'pi_test_refund_source',
        'reference' => 'ANK-2026-0530-D01',
    ]);

    $refund = Payment::factory()->create([
        'booking_id' => $booking->id,
        'kind' => PaymentKind::Refund,
        'method' => PaymentMethod::StripeLink,
        'amount' => -2660,
        'status' => PaymentStatus::Refunded,
        'gateway_id' => 're_test_001',
        'reference' => 'ANK-2026-0530-R01',
    ]);

    $signed = FakeStripeGateway::signedEvent(chargeRefundedEvent('evt_refund_1', 're_test_001'));

    $this->call(
        'POST',
        '/api/stripe/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signed['signature'],
        ],
        $signed['payload'],
    )->assertOk();

    $refund->refresh();
    $settlement->refresh();

    expect($refund->status)->toBe(PaymentStatus::Refunded);
    expect($refund->gateway_id)->toBe('re_test_001');
    expect($refund->amount)->toBe(-2660);
    expect($settlement->status)->toBe(PaymentStatus::Settled);
    expect($settlement->gateway_id)->toBe('pi_test_refund_source');
    expect(Payment::query()->where('kind', PaymentKind::Refund)->count())->toBe(1);
});

test('charge.refunded without a matching refund row is unmatched and creates nothing', function (): void {
    $signed = FakeStripeGateway::signedEvent(chargeRefundedEvent('evt_refund_2', 're_test_missing'));

    $this->call(
        'POST',
        '/api/stripe/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signed['signature'],
        ],
        $signed['payload'],
    )->assertOk();

    $event = StripeEvent::query()->where('stripe_event_id', 'evt_refund_2')->firstOrFail();
    expect($event->processed_at)->not->toBeNull();
    expect($event->resolution)->toBe('unmatched');
    expect($event->error)->toBeNull();
    expect(Payment::query()->count())->toBe(0);
});
