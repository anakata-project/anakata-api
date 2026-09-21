<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\ChangeHistory;
use App\Models\Role;
use App\Support\Roles\GrantContactsMerge;
use Database\Seeders\RolesSeeder;

test('granting contacts.merge to manager is idempotent', function (): void {
    $this->seed(RolesSeeder::class);

    $role = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();
    $role->permissions = $role->permissions
        ->reject(fn (Permission $permission): bool => $permission === Permission::ContactsMerge)
        ->values();
    $role->save();

    expect($role->fresh()?->permissions->contains(Permission::ContactsMerge))->toBeFalse();

    GrantContactsMerge::grant();

    $granted = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();
    expect($granted->permissions->contains(Permission::ContactsMerge))->toBeTrue();

    expect(Role::query()->where('slug', SystemRole::SalesExec->value)->firstOrFail()
        ->permissions->contains(Permission::ContactsMerge))->toBeFalse();

    expect(ChangeHistory::query()
        ->where('event', 'role.updated')
        ->where('reason', GrantContactsMerge::REASON)
        ->count())->toBe(1);

    GrantContactsMerge::grant();

    expect(ChangeHistory::query()
        ->where('event', 'role.updated')
        ->where('reason', GrantContactsMerge::REASON)
        ->count())->toBe(1);
});
