<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;

test('admin has every permission even when stored json is empty', function (): void {
    $user = User::factory()->withRole(SystemRole::Admin)->create();

    expect($user->role?->getRawOriginal('permissions'))->toBe('[]');

    foreach (Permission::cases() as $permission) {
        expect($user->hasPermission($permission))->toBeTrue();
        expect($user->can($permission->value))->toBeTrue();
    }

    expect($user->permissions())->toHaveCount(count(Permission::cases()));
    expect($user->sections())->toBe(['rms', 'crm']);
});

test('a non-admin can a permission only when the role holds it', function (): void {
    $role = Role::factory()->create([
        'permissions' => [Permission::RefundsApprove],
    ]);
    $user = User::factory()->create(['role_id' => $role->id]);

    expect($user->can('refunds.approve'))->toBeTrue();
    expect($user->can('bookings.create'))->toBeFalse();
    expect($user->hasPermission(Permission::RefundsApprove))->toBeTrue();
    expect($user->hasPermission(Permission::BookingsCreate))->toBeFalse();
});

test('sales exec cannot approve refunds', function (): void {
    $user = User::factory()->withRole(SystemRole::SalesExec)->create();

    expect($user->can('refunds.approve'))->toBeFalse();
    expect($user->sections())->toBe(['rms', 'crm']);
});

test('a user without a role has no permissions', function (): void {
    $user = User::factory()->create();

    expect($user->hasPermission(Permission::PanelRms))->toBeFalse();
    expect($user->can('panel.rms'))->toBeFalse();
    expect($user->permissions())->toBeEmpty();
    expect($user->sections())->toBe([]);
});
