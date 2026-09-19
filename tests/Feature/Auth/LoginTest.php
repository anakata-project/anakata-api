<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;

function failedLoginBody(): array
{
    return [
        'message' => __('auth.failed'),
        'errors' => [
            'email' => [__('auth.failed')],
        ],
    ];
}

test('login returns the me payload and sets last_login_at', function (): void {
    $user = User::factory()->withRole(SystemRole::Admin)->create([
        'name' => 'Carolina M.',
        'email' => 'carolina@anakata.test',
    ]);

    $response = withPanelCsrf()->postJson('/api/auth/login', [
        'email' => 'Carolina@Anakata.test',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', 'Carolina M.')
        ->assertJsonPath('email', 'carolina@anakata.test')
        ->assertJsonPath('role.slug', 'admin')
        ->assertJsonPath('sections', ['rms', 'crm'])
        ->assertJsonPath('time_zone', 'Pacific/Galapagos');

    expect($response->json('permissions'))->toEqualCanonicalizing(
        collect(Permission::cases())->map->value->all(),
    );

    $this->assertAuthenticatedAs($user->fresh());
    expect($user->fresh()?->last_login_at)->not->toBeNull();
});

test('the four login failures return the same body', function (string $email, string $password): void {
    User::factory()->invited()->withRole(SystemRole::Admin)->create([
        'email' => 'invited@anakata.test',
    ]);
    User::factory()->disabled()->withRole(SystemRole::Admin)->create([
        'email' => 'disabled@anakata.test',
    ]);
    User::factory()->withRole(SystemRole::Admin)->create([
        'email' => 'active@anakata.test',
    ]);

    withPanelCsrf()->postJson('/api/auth/login', [
        'email' => $email,
        'password' => $password,
    ])
        ->assertStatus(422)
        ->assertExactJson(failedLoginBody());
})->with([
    'unknown email' => ['missing@anakata.test', 'password'],
    'wrong password' => ['active@anakata.test', 'not-the-password'],
    'invited' => ['invited@anakata.test', 'password'],
    'disabled' => ['disabled@anakata.test', 'password'],
]);

test('login is rate limited after five attempts per email and ip', function (): void {
    $user = User::factory()->withRole(SystemRole::Admin)->create([
        'email' => 'limited@anakata.test',
    ]);

    for ($i = 0; $i < 5; $i++) {
        withPanelCsrf()->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    withPanelCsrf()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

test('login while already signed in switches the session', function (): void {
    $carolina = User::factory()->withRole(SystemRole::Admin)->create([
        'email' => 'carolina@anakata.test',
    ]);
    $mateo = User::factory()->withRole(SystemRole::Manager)->create([
        'email' => 'mateo@anakata.test',
    ]);

    withPanelCsrf()->postJson('/api/auth/login', [
        'email' => $carolina->email,
        'password' => 'password',
    ])->assertOk();

    withPanelCsrf()->postJson('/api/auth/login', [
        'email' => $mateo->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('id', $mateo->id);

    $this->withHeaders(panelHeaders())
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('id', $mateo->id);
});

test('cfo me payload lists only the rms section', function (): void {
    $role = Role::factory()->create([
        'name' => 'External finance',
        'slug' => 'external-finance',
        'permissions' => [
            Permission::PanelRms,
            Permission::BookingsViewAll,
            Permission::PaymentsMarkWireReceived,
            Permission::RefundsExecute,
        ],
    ]);
    $user = User::factory()->create([
        'name' => 'CFO (external)',
        'email' => 'cfo@anakata.test',
        'role_id' => $role->id,
        'status' => UserStatus::Active,
    ]);

    withPanelCsrf()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('sections', ['rms']);
});
