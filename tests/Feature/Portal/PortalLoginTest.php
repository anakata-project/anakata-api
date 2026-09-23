<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\ChangeHistory;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

function failedPortalLoginBody(): array
{
    return [
        'message' => __('auth.failed'),
        'errors' => [
            'email' => [__('auth.failed')],
        ],
    ];
}

test('login returns the me payload and sets last_login_at and history', function (): void {
    $agency = approvedAgency(['name' => 'Blue Latitude']);
    $user = agencyUser(['name' => 'Ana Agent', 'email' => 'ana@agency.test'], $agency);

    $response = withPortalCsrf()->postJson('/api/portal/auth/login', [
        'email' => 'Ana@Agency.test',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', 'Ana Agent')
        ->assertJsonPath('email', 'ana@agency.test')
        ->assertJsonPath('agency.id', $agency->id)
        ->assertJsonPath('agency.name', 'Blue Latitude');

    $this->assertAuthenticatedAs($user->fresh(), 'agency');
    expect($user->fresh()?->last_login_at)->not->toBeNull();

    expect(ChangeHistory::query()->where('event', 'portal.signed_in')->count())->toBe(1);
});

test('portal login failures all return the same neutral body', function (string $emailKind, string $password): void {
    $agency = approvedAgency();
    $active = agencyUser(['email' => 'active@agency.test'], $agency);
    AgencyUser::factory()->disabled()->for($agency, 'agency')->create(['email' => 'disabled@agency.test']);
    AgencyUser::factory()->inviteOnPortalLaunch()->for($agency, 'agency')->create(['email' => 'noaccept@agency.test']);

    $suspendedAgency = approvedAgency();
    $suspendedAgency->forceFill([
        'portal_suspended_at' => now(),
        'portal_suspend_reason' => 'test',
    ])->save();
    agencyUser(['email' => 'suspended@agency.test'], $suspendedAgency);

    $unapprovedAgency = Agency::factory()->pending()->create();
    AgencyUser::factory()->active()->for($unapprovedAgency, 'agency')->create(['email' => 'unapproved@agency.test']);

    $email = match ($emailKind) {
        'unknown' => 'missing@agency.test',
        'wrong_password' => $active->email,
        'disabled' => 'disabled@agency.test',
        'no_accept' => 'noaccept@agency.test',
        'suspended' => 'suspended@agency.test',
        'unapproved' => 'unapproved@agency.test',
    };

    withPortalCsrf()->postJson('/api/portal/auth/login', [
        'email' => $email,
        'password' => $password,
    ])
        ->assertStatus(422)
        ->assertExactJson(failedPortalLoginBody());
})->with([
    'unknown email' => ['unknown', 'password'],
    'wrong password' => ['wrong_password', 'not-the-password'],
    'disabled' => ['disabled', 'password'],
    'never accepted an invite' => ['no_accept', 'password'],
    'suspended agency' => ['suspended', 'password'],
    'unapproved agency' => ['unapproved', 'password'],
]);

test('portal login is rate limited after five attempts per email and ip', function (): void {
    $user = agencyUser(['email' => 'limited@agency.test']);

    for ($i = 0; $i < 5; $i++) {
        withPortalCsrf()->postJson('/api/portal/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    withPortalCsrf()->postJson('/api/portal/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
