<?php

declare(strict_types=1);

use App\Actions\Payments\SettleGatewayPayment;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('two wires can share one bank reference', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0520']);

    $deposit = $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payments', [
            'kind' => PaymentKind::Deposit->value,
            'method' => PaymentMethod::Wire->value,
            'amount' => 2660,
        ])
        ->assertCreated()
        ->json('id');

    $balance = $this->actingAs(adminUser())
        ->postJson('/api/rms/bookings/'.$booking->id.'/payments', [
            'kind' => PaymentKind::Balance->value,
            'method' => PaymentMethod::Wire->value,
            'amount' => 1000,
        ])
        ->assertCreated()
        ->json('id');

    $this->actingAs(adminUser())
        ->postJson('/api/rms/payments/'.$deposit.'/mark-received', [
            'bank_reference' => 'WIRE-SHARED-1',
        ])
        ->assertOk();

    $this->actingAs(adminUser())
        ->postJson('/api/rms/payments/'.$balance.'/mark-received', [
            'bank_reference' => 'WIRE-SHARED-1',
        ])
        ->assertOk();

    expect(Payment::query()->where('gateway_id', 'WIRE-SHARED-1')->count())->toBe(2);
});

test('two stripe settlements with the same payment intent write one row', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0521']);
    $settle = app(SettleGatewayPayment::class);

    $first = $settle->handle($booking, [
        'kind' => PaymentKind::Deposit,
        'method' => PaymentMethod::StripeLink,
        'amount' => 2660,
        'gateway_id' => 'pi_test_shared',
    ], null, system: true);

    $second = $settle->handle($booking->fresh(), [
        'kind' => PaymentKind::Deposit,
        'method' => PaymentMethod::StripeLink,
        'amount' => 2660,
        'gateway_id' => 'pi_test_shared',
    ], null, system: true);

    expect($second->id)->toBe($first->id);
    expect(Payment::query()->where('gateway_id', 'pi_test_shared')->count())->toBe(1);
});

test('the generated stripe_gateway_id unique index is the backstop', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-0522']);

    Payment::factory()->create([
        'booking_id' => $booking->id,
        'kind' => PaymentKind::Deposit,
        'method' => PaymentMethod::StripeLink,
        'amount' => 2660,
        'status' => PaymentStatus::Settled,
        'gateway_id' => 'pi_test_backstop',
        'reference' => 'ANK-2026-0522-D01',
    ]);

    expect(fn () => Payment::factory()->create([
        'booking_id' => $booking->id,
        'kind' => PaymentKind::Balance,
        'method' => PaymentMethod::CardStripe,
        'amount' => 100,
        'status' => PaymentStatus::Settled,
        'gateway_id' => 'pi_test_backstop',
        'reference' => 'ANK-2026-0522-B01',
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('payments')->where('gateway_id', 'pi_test_backstop')->update([
        'gateway_id' => 'pi_test_backstop',
    ]))->not->toThrow(QueryException::class);
});
