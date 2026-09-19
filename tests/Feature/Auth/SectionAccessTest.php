<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;

test('rms and crm require authentication', function (): void {
    $this->getJson('/api/rms')->assertUnauthorized();
    $this->getJson('/api/crm')->assertUnauthorized();
});

test('a user without the section permission is forbidden', function (): void {
    $role = Role::factory()->create([
        'permissions' => [
            Permission::PanelRms,
            Permission::BookingsViewAll,
            Permission::PaymentsMarkWireReceived,
            Permission::RefundsExecute,
        ],
    ]);
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson('/api/rms')
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->actingAs($user)
        ->getJson('/api/crm')
        ->assertForbidden()
        ->assertExactJson(['message' => 'This action is unauthorized.']);
});

test('a user with both sections can reach rms and crm', function (): void {
    $user = User::factory()->withRole(SystemRole::Admin)->create();

    $this->actingAs($user)->getJson('/api/rms')->assertOk();
    $this->actingAs($user)->getJson('/api/crm')->assertOk();
});
