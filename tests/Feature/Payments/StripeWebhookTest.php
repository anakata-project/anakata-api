<?php

declare(strict_types=1);

use App\Actions\Payments\SettleGatewayPayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentLinkStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\UnresolvableStripeEvent;
use App\Jobs\ProcessStripeEvent;
use App\Models\ChangeHistory;
use App\Models\Payment;
use App\Models\StripeEvent;
use App\Services\Stripe\FakeStripeGateway;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

/**
 * @param  array<string, mixed>  $event
 */
function postStripeWebhook(array $event, ?string $signature = null): TestResponse
{
    $signed = FakeStripeGateway::signedEvent($event);

    return test()->call(
        'POST',
        '/api/stripe/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature ?? $signed['signature'],
        ],
        $signed['payload'],
    );
}

function checkoutCompletedEvent(int $bookingId, string $reference, string $paymentLink, string $eventId = 'evt_test_checkout'): array
{
    return [
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_001',
                'payment_intent' => 'pi_test_001',
                'payment_link' => $paymentLink,
                'amount_total' => 266000,
                'metadata' => [
                    'booking_id' => (string) $bookingId,
                    'booking_reference' => $reference,
                    'kind' => PaymentKind::Deposit->value,
                ],
            ],
        ],
    ];
}

test('a valid signature stores the event and dispatches a job', function (): void {
    Queue::fake();
    $booking = pendingCabin(['reference' => 'ANK-2026-0510']);

    postStripeWebhook(checkoutCompletedEvent($booking->id, 'ANK-2026-0510', 'plink_test_001'))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(StripeEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessStripeEvent::class);
});

test('an invalid signature is 400 and stores nothing', function (): void {
    Queue::fake();
    $booking = pendingCabin(['reference' => 'ANK-2026-0511']);

    postStripeWebhook(
        checkoutCompletedEvent($booking->id, 'ANK-2026-0511', 'plink_test_001'),
        't=1,v1=not-a-signature',
    )->assertStatus(400);

    expect(StripeEvent::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('duplicate delivery of the same event id is 200 and writes one payment', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0512']);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertCreated();

    $event = checkoutCompletedEvent($booking->id, 'ANK-2026-0512', 'plink_test_001');

    postStripeWebhook($event)->assertOk();
    postStripeWebhook($event)->assertOk();

    expect(Payment::query()->count())->toBe(1);
    expect(StripeEvent::query()->count())->toBe(1);
    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('two different events for the same payment intent write one payment', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0513']);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertCreated();

    postStripeWebhook(checkoutCompletedEvent($booking->id, 'ANK-2026-0513', 'plink_test_001', 'evt_a'))
        ->assertOk();

    $second = checkoutCompletedEvent($booking->id, 'ANK-2026-0513', 'plink_test_001', 'evt_b');
    $second['data']['object']['id'] = 'cs_test_002';

    postStripeWebhook($second)->assertOk();

    expect(Payment::query()->where('gateway_id', 'pi_test_001')->count())->toBe(1);
    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('a paid deposit link settles the payment, confirms the booking and writes history in order', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0514']);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertCreated();

    postStripeWebhook(checkoutCompletedEvent($booking->id, 'ANK-2026-0514', 'plink_test_001'))
        ->assertOk();

    $payment = Payment::query()->firstOrFail();
    expect($payment->method)->toBe(PaymentMethod::StripeLink);
    expect($payment->status)->toBe(PaymentStatus::Settled);
    expect($payment->amount)->toBe(2660);
    expect($payment->gateway_id)->toBe('pi_test_001');
    expect($payment->kind)->toBe(PaymentKind::Deposit);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
    expect($booking->paymentLinks()->firstOrFail()->status)->toBe(PaymentLinkStatus::Paid);

    $events = ChangeHistory::query()
        ->where('subject_id', $booking->id)
        ->orderBy('id')
        ->pluck('event')
        ->all();

    expect(array_slice($events, 0, 3))->toBe([
        'payment_link.created',
        'payment.recorded',
        'booking.status_changed',
    ]);
});

test('an unresolvable booking leaves the event unprocessed and does not 500 the webhook', function (): void {
    Queue::fake();

    postStripeWebhook(checkoutCompletedEvent(999999, 'ANK-MISSING', 'plink_missing', 'evt_orphan'))
        ->assertOk();

    $event = StripeEvent::query()->where('stripe_event_id', 'evt_orphan')->firstOrFail();
    expect($event->processed_at)->toBeNull();

    expect(fn () => (new ProcessStripeEvent('evt_orphan'))->handle(app(SettleGatewayPayment::class)))
        ->toThrow(UnresolvableStripeEvent::class);

    $event->refresh();
    expect($event->processed_at)->toBeNull();
    expect($event->error)->toContain('Could not resolve a booking');
    expect(Payment::query()->count())->toBe(0);
});
