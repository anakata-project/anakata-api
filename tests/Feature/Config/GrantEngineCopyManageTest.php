<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\ChangeHistory;
use App\Models\Role;
use App\Support\Roles\GrantEngineCopyManage;
use Database\Seeders\RolesSeeder;

test('granting engine_copy.manage to an existing manager role is idempotent', function (): void {
    $this->seed(RolesSeeder::class);

    $role = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();
    $role->permissions = $role->permissions
        ->reject(fn (Permission $permission): bool => $permission === Permission::EngineCopyManage)
        ->values();
    $role->save();

    expect($role->fresh()?->permissions->contains(Permission::EngineCopyManage))->toBeFalse();

    GrantEngineCopyManage::grant();

    $granted = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();
    expect($granted->permissions->contains(Permission::EngineCopyManage))->toBeTrue();

    $history = ChangeHistory::query()
        ->where('event', 'role.updated')
        ->where('reason', GrantEngineCopyManage::REASON)
        ->get();

    expect($history)->toHaveCount(1);
    expect($history->first()?->actor_id)->toBeNull();
    expect($history->first()?->actor_label)->toBe('System');
    expect($history->first()?->after['added'] ?? [])->toBe([Permission::EngineCopyManage->value]);

    GrantEngineCopyManage::grant();

    expect(ChangeHistory::query()
        ->where('event', 'role.updated')
        ->where('reason', GrantEngineCopyManage::REASON)
        ->count())->toBe(1);
});
