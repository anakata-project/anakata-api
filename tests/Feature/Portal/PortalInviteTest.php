<?php

declare(strict_types=1);

use App\Enums\AgencyStatus;
use App\Enums\AgencyUserStatus;
use App\Enums\DeliveryKind;
use App\Jobs\SendPortalInviteMail;
use App\Models\Agency;
use App\Models\ChangeHistory;
use App\Models\Delivery;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('approving an agency invites every qualifying user exactly once', function (): void {
    Bus::fake();

    $agency = Agency::factory()->pending()->create();
    $onApproval = $agency->users()->create([
        'name' => 'First user',
        'email' => 'first@agency.test',
        'status' => AgencyUserStatus::InviteOnApproval,
    ]);
    $onLaunch = $agency->users()->create([
        'name' => 'Second user',
        'email' => 'second@agency.test',
        'status' => AgencyUserStatus::InviteOnPortalLaunch,
    ]);

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/decide", [
            'decision' => AgencyStatus::Approved->value,
        ])
        ->assertOk();

    expect(Delivery::query()->where('kind', DeliveryKind::PortalInvite)->count())->toBe(2);
    expect(ChangeHistory::query()->where('event', 'portal.invited')->count())->toBe(2);

    Bus::assertDispatched(SendPortalInviteMail::class, 2);

    $onApproval->refresh();
    $onLaunch->refresh();
    expect($onApproval->invite_token_hash)->not->toBeNull();
    expect($onLaunch->invite_token_hash)->not->toBeNull();
    expect($onApproval->status)->toBe(AgencyUserStatus::InviteOnPortalLaunch);
});

test('the manual resend endpoint issues a fresh token that invalidates the old one', function (): void {
    Bus::fake();

    $agency = approvedAgency();
    $user = $agency->users()->create([
        'name' => 'Agent',
        'email' => 'agent@agency.test',
        'status' => AgencyUserStatus::InviteOnPortalLaunch,
    ]);

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/users/{$user->id}/invite")
        ->assertOk();

    $user->refresh();
    $firstHash = $user->invite_token_hash;
    expect($firstHash)->not->toBeNull();

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/users/{$user->id}/invite")
        ->assertOk();

    $user->refresh();
    expect($user->invite_token_hash)->not->toBe($firstHash);

    expect(Delivery::query()->where('kind', DeliveryKind::PortalInvite)->count())->toBe(2);
});

test('inviting a user from another agency is a 404', function (): void {
    Bus::fake();

    $agency = approvedAgency();
    $other = approvedAgency();
    $user = $other->users()->create([
        'name' => 'Agent',
        'email' => 'agent@other.test',
        'status' => AgencyUserStatus::InviteOnPortalLaunch,
    ]);

    $this->actingAs(managerUser())
        ->postJson("/api/rms/agencies/{$agency->id}/users/{$user->id}/invite")
        ->assertNotFound();
});
