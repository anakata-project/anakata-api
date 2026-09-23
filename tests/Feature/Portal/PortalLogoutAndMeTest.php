<?php

declare(strict_types=1);

use App\Models\ChangeHistory;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('unauthenticated portal me is 401 json', function (): void {
    $this->withHeaders(portalHeaders())
        ->getJson('/api/portal/auth/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

test('me returns the signed-in agency user', function (): void {
    $agency = approvedAgency(['name' => 'Blue Latitude']);
    $user = agencyUser(['name' => 'Ana Agent'], $agency);

    $this->actingAs($user, 'agency')
        ->withHeaders(portalHeaders())
        ->getJson('/api/portal/auth/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', 'Ana Agent')
        ->assertJsonPath('agency.name', 'Blue Latitude');
});

test('logout invalidates the session and writes history', function (): void {
    $user = agencyUser();

    withPortalCsrf()->postJson('/api/portal/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->postJson('/api/portal/auth/logout')->assertNoContent();

    Auth::forgetGuards();

    $this->getJson('/api/portal/auth/me')->assertUnauthorized();

    expect(ChangeHistory::query()->where('event', 'portal.signed_out')->count())->toBe(1);
});
