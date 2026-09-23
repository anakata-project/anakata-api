<?php

declare(strict_types=1);

use App\Models\AgencyUser;
use App\Models\ChangeHistory;
use App\Notifications\PortalResetPasswordNotification;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('portal forgot password always returns the same message', function (string $emailKind): void {
    Notification::fake();

    $active = agencyUser(['email' => 'active@agency.test']);
    AgencyUser::factory()->disabled()->for($active->agency, 'agency')->create(['email' => 'disabled@agency.test']);

    $email = $emailKind === 'known' ? $active->email : ($emailKind === 'disabled' ? 'disabled@agency.test' : 'missing@agency.test');

    withPortalCsrf()->postJson('/api/portal/auth/forgot', [
        'email' => $email,
    ])
        ->assertOk()
        ->assertJsonPath('message', __('passwords.sent'));
})->with([
    'known' => ['known'],
    'unknown' => ['unknown'],
    'disabled' => ['disabled'],
]);

test('portal forgot password notifies only an active agency user', function (): void {
    Notification::fake();

    $active = agencyUser(['email' => 'active@agency.test']);
    $disabled = AgencyUser::factory()->disabled()->for($active->agency, 'agency')->create(['email' => 'disabled@agency.test']);

    withPortalCsrf()->postJson('/api/portal/auth/forgot', ['email' => $active->email])->assertOk();
    withPortalCsrf()->postJson('/api/portal/auth/forgot', ['email' => $disabled->email])->assertOk();
    withPortalCsrf()->postJson('/api/portal/auth/forgot', ['email' => 'missing@agency.test'])->assertOk();

    Notification::assertSentTo($active, PortalResetPasswordNotification::class);
    Notification::assertNotSentTo($disabled, PortalResetPasswordNotification::class);
    Notification::assertSentTimes(PortalResetPasswordNotification::class, 1);
});

test('portal reset password with a valid token updates the password and writes history', function (): void {
    $user = agencyUser(['email' => 'active@agency.test']);
    $token = Password::broker('agency_users')->createToken($user);

    withPortalCsrf()->postJson('/api/portal/auth/reset', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-12',
        'password_confirmation' => 'new-password-12',
    ])
        ->assertOk()
        ->assertJsonPath('message', __('passwords.reset'));

    expect(Hash::check('new-password-12', $user->fresh()?->getAuthPassword() ?? ''))->toBeTrue();
    $this->assertGuest('agency');

    $entry = ChangeHistory::query()->where('event', 'portal.password_reset')->first();
    expect($entry)->not->toBeNull();
    expect($entry?->actor_label)->toBe("{$user->name} ({$user->email})");
});

test('portal reset password rejects an expired token', function (): void {
    $user = agencyUser();
    $token = Password::broker('agency_users')->createToken($user);

    $this->travel(61)->minutes();

    withPortalCsrf()->postJson('/api/portal/auth/reset', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-12',
        'password_confirmation' => 'new-password-12',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});
