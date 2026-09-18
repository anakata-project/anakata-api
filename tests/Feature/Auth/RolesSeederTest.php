<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use Database\Seeders\RolesSeeder;

test('running the seeder twice leaves three roles and keeps an edited manager', function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(RolesSeeder::class);

    expect(Role::query()->count())->toBe(3);

    $manager = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();
    $manager->permissions = [Permission::PanelRms];
    $manager->save();

    $this->seed(RolesSeeder::class);

    expect(Role::query()->count())->toBe(3);

    $manager->refresh();
    expect($manager->permissions->map->value->all())->toBe([Permission::PanelRms->value]);
});

test('the seeder writes the listed defaults for manager and sales exec', function (): void {
    $this->seed(RolesSeeder::class);

    $manager = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();
    $salesExec = Role::query()->where('slug', SystemRole::SalesExec->value)->firstOrFail();
    $admin = Role::query()->where('slug', SystemRole::Admin->value)->firstOrFail();

    expect($manager->permissions->map->value->sort()->values()->all())
        ->toBe(SystemRole::Manager->defaultPermissions()->map->value->sort()->values()->all());
    expect($salesExec->permissions->map->value->sort()->values()->all())
        ->toBe(SystemRole::SalesExec->defaultPermissions()->map->value->sort()->values()->all());
    expect($admin->getRawOriginal('permissions'))->toBe('[]');
    expect($admin->is_system)->toBeTrue();
    expect($manager->is_system)->toBeTrue();
    expect($salesExec->is_system)->toBeTrue();
});
