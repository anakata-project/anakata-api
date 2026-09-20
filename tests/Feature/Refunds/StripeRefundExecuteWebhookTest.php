<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Payment;
use App\Services\Stripe\FakeStripeGateway;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

/**
 * @return array<string, mixed>
 */
function chargeRefundedExecuteEvent(string $eventId, string $refundId): array
{
    return [
        'id' => $eventId,
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_test_refund_execute',
                'payment_intent' => 'pi_test_refund_execute',
                'amount' => 266000,
                'refunds' => [
                    'data' => [
                        ['id' => $refundId, 'amount' => 133000],
                    ],
                ],
            ],
        ],
    ];
}

test('settle then execute then charge.refunded stamps the same refund row', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-11 12:00:00', 'Pacific/Galapagos'));
    $booking = refundCabin([
        'departure' => ReservationFixtures::anamaraDeparture('2027-11-07'),
        'status' => BookingStatus::Confirmed,
        'reference' => 'ANK-2026-0531',
        'cabin_code' => 'S4',
    ]);

    $settlement = Payment::factory()->create([
        'booking_id' => $booking->id,
        'kind' => PaymentKind::Deposit,
        'method' => PaymentMethod::CardStripe,
        'amount' => 2660,
        'status' => PaymentStatus::Settled,
        'gateway_id' => 'pi_test_refund_execute',
        'reference' => 'ANK-2026-0531-D01',
    ]);

    $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/transition', [
            'to' => 'CANCELLED',
            'reason' => 'Guest withdrew',
        ])
        ->assertOk();

    $refundId = $booking->fresh()?->refundRequest?->id;
    expect($refundId)->toBeInt();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/decide', [
            'decision' => RefundRequestStatus::Approved->value,
            'reason' => 'Band is correct',
        ])
        ->assertOk();

    $this->actingAs(externalFinanceUser())
        ->postJson('/api/rms/refunds/'.$refundId.'/execute', [
            'method' => PaymentMethod::CardStripe->value,
            'reference' => 're_test_execute_001',
        ])
        ->assertOk();

    $refund = Payment::query()
        ->where('booking_id', $booking->id)
        ->where('kind', PaymentKind::Refund)
        ->firstOrFail();

    expect($refund->method)->toBe(PaymentMethod::CardStripe);
    expect($refund->gateway_id)->toBe('re_test_execute_001');
    expect($refund->amount)->toBe(-1330);
    expect($refund->status)->toBe(PaymentStatus::Refunded);

    $ids = DB::table('payments')
        ->whereIn('id', [$settlement->id, $refund->id])
        ->pluck('stripe_gateway_id', 'id');

    expect($ids[$settlement->id])->toBe('pi_test_refund_execute');
    expect($ids[$refund->id])->toBe('re_test_execute_001');
    expect($ids[$settlement->id])->not->toBe($ids[$refund->id]);

    $signed = FakeStripeGateway::signedEvent(chargeRefundedExecuteEvent('evt_refund_execute_1', 're_test_execute_001'));

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

    expect($refund->id)->toBe($refund->id);
    expect(Payment::query()->where('kind', PaymentKind::Refund)->count())->toBe(1);
    expect($refund->gateway_id)->toBe('re_test_execute_001');
    expect($refund->status)->toBe(PaymentStatus::Refunded);
    expect($settlement->gateway_id)->toBe('pi_test_refund_execute');
    expect($settlement->status)->toBe(PaymentStatus::Settled);
    expect($settlement->amount)->toBe(2660);
});
