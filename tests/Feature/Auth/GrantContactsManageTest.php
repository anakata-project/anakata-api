<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\ChangeHistory;
use App\Models\Role;
use App\Support\Roles\GrantContactsManage;
use Database\Seeders\RolesSeeder;

test('granting contacts.manage to existing sales roles is idempotent', function (): void {
    $this->seed(RolesSeeder::class);

    foreach ([SystemRole::Manager, SystemRole::SalesExec] as $systemRole) {
        $role = Role::query()->where('slug', $systemRole->value)->firstOrFail();
        $role->permissions = $role->permissions
            ->reject(fn (Permission $permission): bool => $permission === Permission::ContactsManage)
            ->values();
        $role->save();

        expect($role->fresh()?->permissions->contains(Permission::ContactsManage))->toBeFalse();
    }

    GrantContactsManage::grant();

    foreach ([SystemRole::Manager, SystemRole::SalesExec] as $systemRole) {
        $granted = Role::query()->where('slug', $systemRole->value)->firstOrFail();
        expect($granted->permissions->contains(Permission::ContactsManage))->toBeTrue();
    }

    $history = ChangeHistory::query()
        ->where('event', 'role.updated')
        ->where('reason', GrantContactsManage::REASON)
        ->get();

    expect($history)->toHaveCount(2);
    expect($history->every(fn (ChangeHistory $row): bool => $row->actor_label === 'System'))->toBeTrue();

    GrantContactsManage::grant();

    expect(ChangeHistory::query()
        ->where('event', 'role.updated')
        ->where('reason', GrantContactsManage::REASON)
        ->count())->toBe(2);
});
