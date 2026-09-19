<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\ChangeHistory;
use App\Models\Role;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

test('an admin can list roles with users_count and expanded admin permissions', function (): void {
    $admin = adminUser();
    managerUser();

    $response = $this->actingAs($admin)->getJson('/api/rms/roles');

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toBeArray();

    $adminRole = collect($data)->firstWhere('slug', 'admin');
    expect($adminRole)->not->toBeNull();
    expect($adminRole['is_admin'])->toBeTrue();
    expect($adminRole['is_system'])->toBeTrue();
    expect($adminRole['users_count'])->toBe(1);
    expect($adminRole['permissions'])->toEqual(
        array_map(fn (Permission $permission): string => $permission->value, Permission::cases()),
    );

    $manager = collect($data)->firstWhere('slug', 'manager');
    expect($manager['is_admin'])->toBeFalse();
    expect($manager['permissions'])->toEqualCanonicalizing(
        SystemRole::Manager->defaultPermissions()->map(fn (Permission $permission): string => $permission->value)->all(),
    );
});

test('creating a role derives a unique slug and writes history', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/rms/roles', [
            'name' => 'Ops desk',
            'description' => 'Custom',
            'permissions' => [Permission::PanelRms->value],
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Ops desk')
        ->assertJsonPath('slug', 'ops-desk')
        ->assertJsonPath('is_system', false)
        ->assertJsonPath('is_admin', false)
        ->assertJsonPath('users_count', 0)
        ->assertJsonPath('permissions', [Permission::PanelRms->value]);

    $role = Role::query()->where('slug', 'ops-desk')->first();
    expect($role)->not->toBeNull();

    $entry = ChangeHistory::query()->where('event', 'role.created')->where('subject_id', $role?->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry?->actor_id)->toBe($admin->id);
});

test('Admin! becomes admin-2 and is not treated as Admin', function (): void {
    $this->actingAs(adminUser())
        ->postJson('/api/rms/roles', [
            'name' => 'Admin!',
            'permissions' => [],
        ])
        ->assertCreated()
        ->assertJsonPath('slug', 'admin-2')
        ->assertJsonPath('is_admin', false)
        ->assertJsonPath('permissions', []);

    $role = Role::query()->where('slug', 'admin-2')->first();
    expect($role?->isAdmin())->toBeFalse();
});

test('Sales-Exec becomes sales-exec-2', function (): void {
    $this->actingAs(adminUser())
        ->postJson('/api/rms/roles', [
            'name' => 'Sales-Exec',
            'permissions' => [Permission::PanelRms->value],
        ])
        ->assertCreated()
        ->assertJsonPath('slug', 'sales-exec-2');
});

test('creating a role rejects unknown permissions and duplicate names', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/rms/roles', [
            'name' => 'Broken',
            'permissions' => ['not.a.permission'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['permissions.0']);

    $this->actingAs($admin)
        ->postJson('/api/rms/roles', [
            'name' => 'Admin',
            'permissions' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('updating a custom role writes a sorted permission diff', function (): void {
    $admin = adminUser();
    $role = Role::factory()->create([
        'name' => 'Desk',
        'permissions' => [Permission::PanelRms, Permission::UsersManage],
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/rms/roles/{$role->id}", [
            'name' => 'Front desk',
            'permissions' => [
                Permission::PanelRms->value,
                Permission::RolesManage->value,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Front desk')
        ->assertJsonPath('slug', $role->slug);

    $entry = ChangeHistory::query()->where('event', 'role.updated')->where('subject_id', $role->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry?->actor_id)->toBe($admin->id);
    expect($entry?->before['permissions'])->toBe([
        Permission::PanelRms->value,
        Permission::UsersManage->value,
    ]);
    expect($entry?->after['permissions'])->toBe([
        Permission::PanelRms->value,
        Permission::RolesManage->value,
    ]);
    expect($entry?->after['added'])->toBe([Permission::RolesManage->value]);
    expect($entry?->after['removed'])->toBe([Permission::UsersManage->value]);
});

test('a no-op role patch writes no history', function (): void {
    $role = Role::factory()->create([
        'name' => 'Desk',
        'permissions' => [Permission::PanelRms],
    ]);

    $this->actingAs(adminUser())
        ->patchJson("/api/rms/roles/{$role->id}", [
            'name' => 'Desk',
            'permissions' => [Permission::PanelRms->value],
        ])
        ->assertOk();

    expect(ChangeHistory::query()->where('event', 'role.updated')->count())->toBe(0);
});

test('deleting a custom role writes history with the name and returns 204', function (): void {
    $admin = adminUser();
    $role = Role::factory()->create(['name' => 'Disposable']);

    $this->actingAs($admin)
        ->deleteJson("/api/rms/roles/{$role->id}")
        ->assertNoContent();

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();

    $entry = ChangeHistory::query()->where('event', 'role.deleted')->first();
    expect($entry?->subject_label)->toBe('Disposable');
    expect($entry?->actor_id)->toBe($admin->id);
});

test('role history is paginated newest first', function (): void {
    $admin = adminUser();
    $role = Role::factory()->create();

    $this->actingAs($admin)->patchJson("/api/rms/roles/{$role->id}", [
        'description' => 'one',
    ])->assertOk();
    $this->actingAs($admin)->patchJson("/api/rms/roles/{$role->id}", [
        'description' => 'two',
    ])->assertOk();

    $response = $this->actingAs($admin)->getJson("/api/rms/roles/{$role->id}/history");
    $response->assertOk();

    $events = collect($response->json('data'))->pluck('event')->all();
    expect($events[0])->toBe('role.updated');
    expect($response->json('data.0.after.description'))->toBe('two');
});
