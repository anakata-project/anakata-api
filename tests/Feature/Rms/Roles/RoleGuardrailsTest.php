<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\ChangeHistory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

test('the admin role cannot be renamed, have permissions patched, or be deleted', function (): void {
    $admin = adminUser();
    $role = Role::query()->where('slug', SystemRole::Admin->value)->firstOrFail();

    $this->actingAs($admin)
        ->patchJson("/api/rms/roles/{$role->id}", ['name' => 'Root'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'The Admin role cannot be renamed.');

    $this->actingAs($admin)
        ->patchJson("/api/rms/roles/{$role->id}", [
            'permissions' => [Permission::PanelRms->value],
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'The Admin role permissions cannot be edited.');

    $this->actingAs($admin)
        ->deleteJson("/api/rms/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'The Admin role cannot be deleted.');
});

test('the admin role can receive a description-only patch', function (): void {
    $admin = adminUser();
    $role = Role::query()->where('slug', SystemRole::Admin->value)->firstOrFail();

    $this->actingAs($admin)
        ->patchJson("/api/rms/roles/{$role->id}", ['description' => 'Full access'])
        ->assertOk()
        ->assertJsonPath('description', 'Full access')
        ->assertJsonPath('name', 'Admin');
});

test('system roles cannot be renamed or deleted but their permissions may change', function (): void {
    $admin = adminUser();
    $manager = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();

    $this->actingAs($admin)
        ->patchJson("/api/rms/roles/{$manager->id}", ['name' => 'Lead'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'A system role cannot be renamed.');

    $this->actingAs($admin)
        ->deleteJson("/api/rms/roles/{$manager->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'A system role cannot be deleted.');

    $this->actingAs($admin)
        ->patchJson("/api/rms/roles/{$manager->id}", [
            'permissions' => [
                Permission::PanelRms->value,
                Permission::BookingsDelete->value,
            ],
        ])
        ->assertOk();

    $manager->refresh();
    expect($manager->permissions->contains(Permission::BookingsDelete))->toBeTrue();
});

test('a role with users cannot be deleted and the message includes the count', function (): void {
    $role = Role::factory()->create();
    User::factory()->create(['role_id' => $role->id]);
    User::factory()->create(['role_id' => $role->id]);

    $this->actingAs(adminUser())
        ->deleteJson("/api/rms/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This role still has 2 users.');

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
    expect(ChangeHistory::query()->where('event', 'role.deleted')->count())->toBe(0);
});
