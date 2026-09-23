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

test('suspending portal access requires a reason and the agencies.manage permission', function (): void {
    $agency = approvedAgency();

    $this->actingAs(salesExecUser())
        ->postJson("/api/rms/agencies/{$agency->id}/portal/suspend", ['reason' => 'fraud review'])
        ->assertForbidden();

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/portal/suspend", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/portal/suspend", ['reason' => 'fraud review'])
        ->assertOk()
        ->assertJsonPath('portal_suspended', true);

    $agency->refresh();
    expect($agency->isPortalSuspended())->toBeTrue();
    expect($agency->portal_suspend_reason)->toBe('fraud review');

    $entry = ChangeHistory::query()->where('event', 'agency.portal_suspended')->first();
    expect($entry)->not->toBeNull();
    expect($entry?->reason)->toBe('fraud review');
});

test('a suspended agencys live session ends on its very next request', function (): void {
    $agency = approvedAgency();
    $user = agencyUser([], $agency);

    withPortalCsrf()->postJson('/api/portal/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->withHeaders(portalHeaders())
        ->getJson('/api/portal/auth/me')
        ->assertOk();

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/portal/suspend", ['reason' => 'fraud review'])
        ->assertOk();

    Auth::forgetGuards();

    $this->withHeaders(portalHeaders())
        ->getJson('/api/portal/auth/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);

    Auth::forgetGuards();
    $this->assertGuest('agency');
});

test('sign-in is refused for a suspended agency with the same neutral message', function (): void {
    $agency = approvedAgency();
    $user = agencyUser([], $agency);

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/portal/suspend", ['reason' => 'fraud review'])
        ->assertOk();

    withPortalCsrf()->postJson('/api/portal/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertExactJson([
            'message' => __('auth.failed'),
            'errors' => ['email' => [__('auth.failed')]],
        ]);
});

test('resuming restores access and requires a reason', function (): void {
    $agency = approvedAgency();
    $user = agencyUser([], $agency);

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/portal/suspend", ['reason' => 'fraud review'])
        ->assertOk();

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/portal/resume", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/portal/resume", ['reason' => 'cleared'])
        ->assertOk()
        ->assertJsonPath('portal_suspended', false);

    $agency->refresh();
    expect($agency->isPortalSuspended())->toBeFalse();

    withPortalCsrf()->postJson('/api/portal/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    expect(ChangeHistory::query()->where('event', 'agency.portal_resumed')->count())->toBe(1);
});
