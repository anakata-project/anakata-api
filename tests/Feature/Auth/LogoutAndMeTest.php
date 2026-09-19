<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('me returns the signed-in user', function (): void {
    $user = User::factory()->withRole(SystemRole::Admin)->create([
        'name' => 'Carolina M.',
    ]);

    $this->actingAs($user)
        ->withHeaders(panelHeaders())
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', 'Carolina M.')
        ->assertJsonPath('time_zone', 'Pacific/Galapagos');
});

test('logout invalidates the session', function (): void {
    $user = User::factory()->withRole(SystemRole::Admin)->create();

    withPanelCsrf()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->postJson('/api/auth/logout')->assertNoContent();

    Auth::forgetGuards();

    $this->getJson('/api/auth/me')->assertUnauthorized();
});

test('a disabled user is logged out on the next request', function (): void {
    $user = User::factory()->withRole(SystemRole::Admin)->create();

    $this->actingAs($user);

    $user->forceFill([
        'status' => UserStatus::Disabled,
        'disabled_at' => now(),
    ])->save();

    $this->withHeaders(panelHeaders())
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
});
