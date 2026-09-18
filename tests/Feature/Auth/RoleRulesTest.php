<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use LogicException;

test('updating a role slug after create throws', function (): void {
    $role = Role::factory()->create(['slug' => 'custom-role']);

    $role->slug = 'renamed-role';
    $role->save();
})->throws(LogicException::class, 'A role slug cannot be changed after creation.');

test('saving the admin role with permissions stores an empty list', function (): void {
    $role = Role::factory()->create([
        'name' => SystemRole::Admin->label(),
        'slug' => SystemRole::Admin->value,
        'is_system' => true,
        'permissions' => [],
    ]);

    $role->permissions = [Permission::PanelRms, Permission::RefundsApprove];
    $role->save();

    $role->refresh();

    expect($role->getRawOriginal('permissions'))->toBe('[]');
    expect($role->permissions)->toBeEmpty();
});
