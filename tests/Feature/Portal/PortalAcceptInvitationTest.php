<?php

declare(strict_types=1);

use App\Enums\AgencyUserStatus;
use App\Models\ChangeHistory;
use Database\Factories\AgencyUserFactory;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('accept invitation activates the agency user, writes history and signs them in', function (): void {
    $agency = approvedAgency();
    [$state, $token] = AgencyUserFactory::new()->withPendingInvite();
    $agencyUser = $state->for($agency, 'agency')->create(['email' => 'invitee@agency.test']);

    withPortalCsrf()->postJson('/api/portal/auth/accept', [
        'token' => $token,
        'email' => $agencyUser->email,
        'password' => 'new-password-12',
        'password_confirmation' => 'new-password-12',
    ])
        ->assertOk()
        ->assertJsonPath('id', $agencyUser->id)
        ->assertJsonPath('email', 'invitee@agency.test');

    $agencyUser->refresh();
    expect($agencyUser->status)->toBe(AgencyUserStatus::Active);
    expect($agencyUser->accepted_at)->not->toBeNull();
    expect($agencyUser->invite_token_hash)->toBeNull();
    $this->assertAuthenticatedAs($agencyUser, 'agency');

    $entry = ChangeHistory::query()->where('event', 'portal.accepted')->first();
    expect($entry)->not->toBeNull();
    expect($entry?->subject_type)->toBe('agency');
    expect($entry?->subject_id)->toBe($agency->id);
    expect($entry?->actor_label)->toBe("{$agencyUser->name} ({$agencyUser->email})");
});

test('a second use of an invitation is rejected', function (): void {
    $agency = approvedAgency();
    [$state, $token] = AgencyUserFactory::new()->withPendingInvite();
    $agencyUser = $state->for($agency, 'agency')->create();

    withPortalCsrf()->postJson('/api/portal/auth/accept', [
        'token' => $token,
        'email' => $agencyUser->email,
        'password' => 'new-password-12',
        'password_confirmation' => 'new-password-12',
    ])->assertOk();

    withPortalCsrf()->postJson('/api/portal/auth/accept', [
        'token' => $token,
        'email' => $agencyUser->email,
        'password' => 'other-password-12',
        'password_confirmation' => 'other-password-12',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('an expired invitation is rejected', function (): void {
    $agency = approvedAgency();
    [$state, $token] = AgencyUserFactory::new()->withPendingInvite();
    $agencyUser = $state->for($agency, 'agency')->create([
        'invite_expires_at' => now()->subMinute(),
    ]);

    withPortalCsrf()->postJson('/api/portal/auth/accept', [
        'token' => $token,
        'email' => $agencyUser->email,
        'password' => 'new-password-12',
        'password_confirmation' => 'new-password-12',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});
