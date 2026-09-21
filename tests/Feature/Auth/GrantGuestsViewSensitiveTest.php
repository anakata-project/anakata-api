<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\ChangeHistory;
use App\Models\Role;
use App\Support\Roles\GrantGuestsViewSensitive;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesSeeder;

test('granting guests.view_sensitive to an existing manager role is idempotent', function (): void {
    $this->seed(RolesSeeder::class);

    $role = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();
    $role->permissions = $role->permissions
        ->reject(fn (Permission $permission): bool => $permission === Permission::GuestsViewSensitive)
        ->values();
    $role->save();

    expect($role->fresh()?->permissions->contains(Permission::GuestsViewSensitive))->toBeFalse();

    GrantGuestsViewSensitive::grant();

    $granted = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();
    expect($granted->permissions->contains(Permission::GuestsViewSensitive))->toBeTrue();

    $history = ChangeHistory::query()
        ->where('event', 'role.updated')
        ->where('reason', GrantGuestsViewSensitive::REASON)
        ->get();

    expect($history)->toHaveCount(1);
    expect($history->first()?->actor_id)->toBeNull();
    expect($history->first()?->actor_label)->toBe('System');
    expect($history->first()?->after['added'] ?? [])->toBe([Permission::GuestsViewSensitive->value]);

    GrantGuestsViewSensitive::grant();

    expect(ChangeHistory::query()
        ->where('event', 'role.updated')
        ->where('reason', GrantGuestsViewSensitive::REASON)
        ->count())->toBe(1);
});

test('removing guests.view_sensitive and re-seeding does not bring it back', function (): void {
    $this->seed(RolesSeeder::class);

    GrantGuestsViewSensitive::grant();

    $role = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();
    $role->permissions = $role->permissions
        ->reject(fn (Permission $permission): bool => $permission === Permission::GuestsViewSensitive)
        ->values();
    $role->save();

    expect($role->fresh()?->permissions->contains(Permission::GuestsViewSensitive))->toBeFalse();

    $this->seed(RolesSeeder::class);

    $role->refresh();
    expect($role->permissions->contains(Permission::GuestsViewSensitive))->toBeFalse();
});

test('sales exec and external finance stay without guests.view_sensitive', function (): void {
    $this->seed(RolesSeeder::class);

    $salesExec = Role::query()->where('slug', SystemRole::SalesExec->value)->firstOrFail();
    expect($salesExec->permissions->contains(Permission::GuestsViewSensitive))->toBeFalse();
    expect(SystemRole::SalesExec->defaultPermissions()->contains(Permission::GuestsViewSensitive))->toBeFalse();

    $this->seed(DemoUsersSeeder::class);

    $finance = Role::query()->where('slug', 'external-finance')->firstOrFail();
    expect($finance->permissions->contains(Permission::GuestsViewSensitive))->toBeFalse();
});
