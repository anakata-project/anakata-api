<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use App\Support\SensitiveFields;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
| Feature tests boot the Laravel application and refresh the database.
| Unit tests stay on PHPUnit\Framework\TestCase and do not touch the database.
*/

pest()->extend(TestCase::class)->in('Feature');

function assertNoSensitiveFields(TestResponse $response): void
{
    $json = $response->json();

    expect(SensitiveFields::keysIn(is_array($json) ? $json : []))->toBeEmpty();
}

/**
 * @return array<string, string>
 */
function panelHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Referer' => 'http://localhost:3001/login',
        'Accept' => 'application/json',
    ];
}

function withPanelCsrf(): TestCase
{
    $test = test();
    $test->withHeaders(panelHeaders())->get('/sanctum/csrf-cookie');

    return $test->withHeaders([
        ...panelHeaders(),
        'X-CSRF-TOKEN' => csrf_token() ?: '',
    ]);
}

function adminUser(array $attributes = []): User
{
    return User::factory()->withRole(SystemRole::Admin)->create($attributes);
}

function managerUser(array $attributes = []): User
{
    return User::factory()->withRole(SystemRole::Manager)->create($attributes);
}

function salesExecUser(array $attributes = []): User
{
    return User::factory()->withRole(SystemRole::SalesExec)->create($attributes);
}

function externalFinanceUser(array $attributes = []): User
{
    $role = Role::factory()->create([
        'permissions' => [
            Permission::PanelRms,
            Permission::BookingsViewAll,
            Permission::PaymentsMarkWireReceived,
            Permission::RefundsExecute,
        ],
    ]);

    return User::factory()->create([
        'role_id' => $role->id,
        ...$attributes,
    ]);
}

function limitedAdminRole(): Role
{
    return Role::factory()->create([
        'permissions' => [
            Permission::PanelRms,
            Permission::UsersManage,
            Permission::RolesManage,
        ],
    ]);
}

/**
 * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
 */
function rmsManagementRequests(User $target, Role $role): array
{
    return [
        ['getJson', '/api/rms/permissions', []],
        ['getJson', '/api/rms/roles', []],
        ['postJson', '/api/rms/roles', ['name' => 'Temp role '.uniqid(), 'permissions' => []]],
        ['patchJson', "/api/rms/roles/{$role->id}", ['description' => 'x']],
        ['deleteJson', "/api/rms/roles/{$role->id}", []],
        ['getJson', "/api/rms/roles/{$role->id}/history", []],
        ['getJson', '/api/rms/users', []],
        ['postJson', '/api/rms/users', [
            'name' => 'Invitee',
            'email' => 'invitee-'.uniqid().'@anakata.test',
            'role_id' => $role->id,
        ]],
        ['patchJson', "/api/rms/users/{$target->id}", ['name' => 'Renamed']],
        ['postJson', "/api/rms/users/{$target->id}/disable", []],
        ['postJson', "/api/rms/users/{$target->id}/enable", []],
        ['postJson', "/api/rms/users/{$target->id}/resend-invitation", []],
        ['getJson', "/api/rms/users/{$target->id}/history", []],
    ];
}
