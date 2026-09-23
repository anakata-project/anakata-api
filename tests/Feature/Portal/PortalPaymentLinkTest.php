<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentLinkStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BookingRequest;
use App\Models\ChangeHistory;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\Stripe\FakeStripeGateway;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

/**
 * @param  array<string, mixed>  $event
 */
function postPortalStripeWebhook(array $event): TestResponse
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
            'HTTP_STRIPE_SIGNATURE' => $signed['signature'],
        ],
        $signed['payload'],
    );
}

/**
 * @return array<string, mixed>
 */
function portalCheckoutCompletedEvent(int $bookingId, string $reference, string $paymentLink): array
{
    return [
        'id' => 'evt_portal_checkout',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_portal_001',
                'payment_intent' => 'pi_portal_001',
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

test('an agency user can open a deposit link for their own booking and a client amount is ignored', function (): void {
    $agency = approvedAgency();
    $user = agencyUser(['name' => 'Ana Agent'], $agency);
    $booking = pendingCabin([
        'agency_id' => $agency->id,
        'reference' => 'ANK-2026-1501',
    ]);

    $created = withPortalCsrf()
        ->actingAs($user, 'agency')
        ->postJson('/api/portal/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
            'amount' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('kind', PaymentKind::Deposit->value)
        ->assertJsonPath('amount', $booking->depositAmount())
        ->assertJsonPath('status', PaymentLinkStatus::Open->value);

    $link = PaymentLink::query()->findOrFail($created->json('id'));
    expect($link->created_by)->toBeNull();

    $history = ChangeHistory::query()
        ->where('subject_id', $booking->id)
        ->where('event', 'payment_link.created')
        ->firstOrFail();

    expect($history->actor_id)->toBeNull();
    expect($history->actor_label)->toBe('Ana Agent via portal');
    expect($history->context['agency_user_id'] ?? null)->toBe($user->id);
    expect($history->context['source'] ?? null)->toBe('portal');
    expect($history->after)->toMatchArray([
        'amount' => $booking->depositAmount(),
        'kind' => PaymentKind::Deposit->value,
    ]);
});

test('a staff link on the same booking keeps the staff name and a portal link does not', function (): void {
    $agency = approvedAgency();
    $user = agencyUser(['name' => 'Ana Agent'], $agency);
    $staff = adminUser(['name' => 'Cara Staff']);
    $booking = pendingCabin([
        'agency_id' => $agency->id,
        'reference' => 'ANK-2026-1502',
    ]);

    withPortalCsrf()
        ->actingAs($user, 'agency')
        ->postJson('/api/portal/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertCreated();

    test()->flushSession();
    Auth::forgetGuards();
    Auth::shouldUse('web');

    $this->actingAs($staff)
        ->postJson('/api/rms/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Balance->value,
        ])
        ->assertCreated();

    $labels = ChangeHistory::query()
        ->where('subject_id', $booking->id)
        ->where('event', 'payment_link.created')
        ->orderBy('id')
        ->pluck('actor_label')
        ->all();

    expect($labels)->toBe(['Ana Agent via portal', 'Cara Staff']);
});

test('another agency and a direct booking are refused', function (): void {
    $owner = approvedAgency();
    $other = approvedAgency();
    $user = agencyUser([], $other);
    $theirs = pendingCabin([
        'agency_id' => $owner->id,
        'reference' => 'ANK-2026-1503',
    ]);
    $direct = pendingCabin([
        'reference' => 'ANK-2026-1504',
    ]);

    withPortalCsrf()
        ->actingAs($user, 'agency')
        ->postJson('/api/portal/bookings/'.$theirs->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertForbidden();

    withPortalCsrf()
        ->actingAs($user, 'agency')
        ->postJson('/api/portal/bookings/'.$direct->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertForbidden();

    expect(PaymentLink::query()->count())->toBe(0);
});

test('a second open link of the same kind is refused', function (): void {
    $agency = approvedAgency();
    $user = agencyUser([], $agency);
    $booking = pendingCabin([
        'agency_id' => $agency->id,
        'reference' => 'ANK-2026-1505',
    ]);

    withPortalCsrf()
        ->actingAs($user, 'agency')
        ->postJson('/api/portal/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertCreated();

    withPortalCsrf()
        ->actingAs($user, 'agency')
        ->postJson('/api/portal/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kind');

    expect(PaymentLink::query()->count())->toBe(1);
});

test('a portal link settles through the existing stripe webhook', function (): void {
    $agency = approvedAgency();
    $user = agencyUser([], $agency);
    $booking = pendingCabin([
        'agency_id' => $agency->id,
        'reference' => 'ANK-2026-1506',
    ]);

    $created = withPortalCsrf()
        ->actingAs($user, 'agency')
        ->postJson('/api/portal/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertCreated();

    test()->flushSession();
    Auth::forgetGuards();
    Auth::shouldUse('web');

    postPortalStripeWebhook(portalCheckoutCompletedEvent(
        $booking->id,
        'ANK-2026-1506',
        (string) $created->json('stripe_id'),
    ))->assertOk();

    $payment = Payment::query()->firstOrFail();
    expect($payment->method)->toBe(PaymentMethod::StripeLink);
    expect($payment->status)->toBe(PaymentStatus::Settled);
    expect($payment->amount)->toBe($booking->depositAmount());
    expect($payment->kind)->toBe(PaymentKind::Deposit);
    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
    expect($booking->paymentLinks()->firstOrFail()->status)->toBe(PaymentLinkStatus::Paid);
});

test('an unsigned portal session cannot open a payment link', function (): void {
    $agency = approvedAgency();
    $booking = pendingCabin([
        'agency_id' => $agency->id,
        'reference' => 'ANK-2026-1507',
    ]);

    withPortalCsrf()
        ->postJson('/api/portal/bookings/'.$booking->id.'/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertUnauthorized();

    expect(PaymentLink::query()->count())->toBe(0);
});

test('portal lists name the booking id and only open payment kinds for this agency', function (): void {
    $agency = approvedAgency();
    $other = approvedAgency();
    $user = agencyUser([], $agency);
    $booking = pendingCabin([
        'agency_id' => $agency->id,
        'reference' => 'ANK-2026-1510',
        'departure' => ReservationFixtures::anamaraDeparture('2027-11-07'),
    ]);
    $theirs = pendingCabin([
        'agency_id' => $other->id,
        'reference' => 'ANK-2026-1511',
        'departure' => ReservationFixtures::anamaraDeparture('2027-11-14'),
    ]);
    $requestBooking = pendingCabin([
        'agency_id' => $agency->id,
        'reference' => null,
        'request_reference' => 'ANK-R-2026-1512',
        'status' => BookingStatus::Requested,
        'departure' => ReservationFixtures::anamaraDeparture('2027-11-21'),
    ]);
    BookingRequest::factory()->create(['booking_id' => $requestBooking->id]);

    PaymentLink::factory()->create([
        'booking_id' => $booking->id,
        'kind' => PaymentKind::Deposit,
        'status' => PaymentLinkStatus::Open,
    ]);
    PaymentLink::factory()->create([
        'booking_id' => $booking->id,
        'kind' => PaymentKind::Balance,
        'status' => PaymentLinkStatus::Paid,
    ]);
    PaymentLink::factory()->create([
        'booking_id' => $theirs->id,
        'kind' => PaymentKind::Deposit,
        'status' => PaymentLinkStatus::Open,
    ]);

    $listed = $this->actingAs($user, 'agency')
        ->withHeaders(portalHeaders())
        ->getJson('/api/portal/bookings')
        ->assertOk();

    $row = collect($listed->json('data'))->firstWhere('reference', 'ANK-2026-1510');
    expect($row)->toBeArray()
        ->and($row['id'])->toBe($booking->id)
        ->and($row['open_payment_kinds'])->toBe([PaymentKind::Deposit->value])
        ->and(collect($listed->json('data'))->pluck('id')->all())->not->toContain($theirs->id)
        ->and(collect($listed->json('data'))->pluck('reference')->all())->not->toContain('ANK-2026-1511');

    $requests = $this->actingAs($user, 'agency')
        ->withHeaders(portalHeaders())
        ->getJson('/api/portal/requests')
        ->assertOk()
        ->assertJsonPath('data.0.id', $requestBooking->id)
        ->assertJsonPath('data.0.payment_state', 'Awaiting deposit')
        ->assertJsonPath('data.0.open_payment_kinds', []);

    expect(collect($requests->json('data'))->pluck('id')->all())->not->toContain($theirs->id);
});

test('an unknown booking id is not found', function (): void {
    $user = agencyUser();

    withPortalCsrf()
        ->actingAs($user, 'agency')
        ->postJson('/api/portal/bookings/999999/payment-link', [
            'kind' => PaymentKind::Deposit->value,
        ])
        ->assertNotFound();
});
