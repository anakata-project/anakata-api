<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\ChangeHistory;
use App\Models\Role;
use App\Support\Roles\GrantCommissionsRecordPayout;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesSeeder;

test('granting commissions.record_payout to external finance is idempotent', function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);

    $role = Role::query()->where('slug', 'external-finance')->firstOrFail();
    $role->permissions = $role->permissions
        ->reject(fn (Permission $permission): bool => $permission === Permission::CommissionsRecordPayout)
        ->values();
    $role->save();

    expect($role->fresh()?->permissions->contains(Permission::CommissionsRecordPayout))->toBeFalse();

    GrantCommissionsRecordPayout::grant();

    $granted = Role::query()->where('slug', 'external-finance')->firstOrFail();
    expect($granted->permissions->contains(Permission::CommissionsRecordPayout))->toBeTrue();

    $history = ChangeHistory::query()
        ->where('event', 'role.updated')
        ->where('reason', GrantCommissionsRecordPayout::REASON)
        ->get();

    expect($history)->toHaveCount(1);
    expect($history->first()?->actor_label)->toBe('System');
    expect($history->first()?->after['added'] ?? [])->toBe([Permission::CommissionsRecordPayout->value]);

    GrantCommissionsRecordPayout::grant();

    expect(ChangeHistory::query()
        ->where('event', 'role.updated')
        ->where('reason', GrantCommissionsRecordPayout::REASON)
        ->count())->toBe(1);
});
