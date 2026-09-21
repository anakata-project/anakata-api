<?php

declare(strict_types=1);

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

test('rms users can read payment kind and method options', function (): void {
    $response = $this->actingAs(salesExecUser())
        ->getJson('/api/rms/payments/options')
        ->assertOk();

    $kinds = collect($response->json('kinds'));
    expect($kinds->pluck('value')->all())->toBe(array_map(
        fn (PaymentKind $kind): string => $kind->value,
        PaymentKind::cases(),
    ));
    expect($kinds->firstWhere('value', PaymentKind::Refund->value))->toMatchArray([
        'value' => PaymentKind::Refund->value,
        'label' => PaymentKind::Refund->label(),
        'recordable' => false,
    ]);
    expect($kinds->firstWhere('value', PaymentKind::Deposit->value))->toMatchArray([
        'value' => PaymentKind::Deposit->value,
        'label' => PaymentKind::Deposit->label(),
        'recordable' => true,
    ]);

    $methods = collect($response->json('methods'));
    expect($methods->pluck('value')->all())->toBe(array_map(
        fn (PaymentMethod $method): string => $method->value,
        PaymentMethod::cases(),
    ));
    expect($methods->every(fn (array $method): bool => $method['recordable'] === true))->toBeTrue();
    expect($methods->firstWhere('value', PaymentMethod::Wire->value)['label'])
        ->toBe(PaymentMethod::Wire->label());
});

test('external finance can read payment options', function (): void {
    $this->actingAs(externalFinanceUser())
        ->getJson('/api/rms/payments/options')
        ->assertOk()
        ->assertJsonPath('kinds.0.value', PaymentKind::Deposit->value);
});

test('payment options require panel.rms', function (): void {
    $role = Role::factory()->create([
        'permissions' => [Permission::PanelCrm],
    ]);
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson('/api/rms/payments/options')
        ->assertForbidden();
});
