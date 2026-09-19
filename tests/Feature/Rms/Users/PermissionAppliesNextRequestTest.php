<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

test('a role permission change applies on the affected users next request without signing in again', function (): void {
    $admin = adminUser();
    $role = Role::factory()->create([
        'permissions' => [
            Permission::PanelRms,
            Permission::UsersManage,
        ],
    ]);
    $operator = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($operator)
        ->getJson('/api/rms/users')
        ->assertOk();

    $this->actingAs($admin)
        ->patchJson("/api/rms/roles/{$role->id}", [
            'permissions' => [Permission::PanelRms->value],
        ])
        ->assertOk();

    Auth::forgetGuards();

    $this->actingAs($operator->fresh())
        ->getJson('/api/rms/users')
        ->assertForbidden();
});
