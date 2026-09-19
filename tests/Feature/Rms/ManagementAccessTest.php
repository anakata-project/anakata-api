<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

test('management endpoints require authentication', function (): void {
    $target = User::factory()->create();
    $role = Role::factory()->create();

    foreach (rmsManagementRequests($target, $role) as [$method, $uri, $payload]) {
        $this->{$method}($uri, $payload)->assertUnauthorized();
    }
});

test('a user without panel.rms cannot reach management endpoints', function (): void {
    $role = Role::factory()->create([
        'permissions' => [
            Permission::PanelCrm,
            Permission::UsersManage,
            Permission::RolesManage,
        ],
    ]);
    $actor = User::factory()->create(['role_id' => $role->id]);
    $target = User::factory()->create();
    $other = Role::factory()->create();

    $this->actingAs($actor);

    foreach (rmsManagementRequests($target, $other) as [$method, $uri, $payload]) {
        $this->{$method}($uri, $payload)
            ->assertForbidden()
            ->assertExactJson(['message' => 'This action is unauthorized.']);
    }
});

test('manager cannot access management endpoints', function (): void {
    $actor = managerUser();
    $target = salesExecUser();
    $role = Role::factory()->create();

    $this->actingAs($actor);

    foreach (rmsManagementRequests($target, $role) as [$method, $uri, $payload]) {
        $this->{$method}($uri, $payload)->assertForbidden();
    }
});

test('sales exec cannot access management endpoints', function (): void {
    $actor = salesExecUser();
    $target = managerUser();
    $role = Role::factory()->create();

    $this->actingAs($actor);

    foreach (rmsManagementRequests($target, $role) as [$method, $uri, $payload]) {
        $this->{$method}($uri, $payload)->assertForbidden();
    }
});

test('external finance cannot access management endpoints', function (): void {
    $actor = externalFinanceUser();
    $target = managerUser();
    $role = Role::factory()->create();

    $this->actingAs($actor);

    foreach (rmsManagementRequests($target, $role) as [$method, $uri, $payload]) {
        $this->{$method}($uri, $payload)->assertForbidden();
    }
});
