<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\User;

test('the permission catalogue lists every case in enum order', function (): void {
    $this->actingAs(adminUser())
        ->getJson('/api/rms/permissions')
        ->assertOk();

    $response = $this->actingAs(adminUser())->getJson('/api/rms/permissions');
    $data = $response->json('data');

    expect($data)->toBeArray();
    expect($data)->toHaveCount(count(Permission::cases()));

    foreach (Permission::cases() as $index => $permission) {
        expect($data[$index]['value'])->toBe($permission->value);
        expect($data[$index]['label'])->toBe($permission->label());
        expect($data[$index]['group'])->toBe($permission->group());
        expect($data[$index]['group_label'])->toBe(Permission::groupLabel($permission->group()));
        expect($data[$index]['is_flag'])->toBe($permission->isFlag());
    }
});

test('a user with only users.manage can read the catalogue', function (): void {
    $role = limitedAdminRole();
    $role->update([
        'permissions' => [
            Permission::PanelRms,
            Permission::UsersManage,
        ],
    ]);
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user)->getJson('/api/rms/permissions')->assertOk();
});
