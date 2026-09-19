<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Models\ChangeHistory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

test('a user cannot disable themself or change their own role', function (): void {
    $first = adminUser(['name' => 'Carolina']);
    $second = adminUser(['name' => 'Backup']);
    $manager = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();

    $this->actingAs($first)
        ->postJson("/api/rms/users/{$first->id}/disable")
        ->assertStatus(409)
        ->assertJsonPath('message', 'You cannot disable your own account.');

    $this->actingAs($first)
        ->patchJson("/api/rms/users/{$first->id}", ['role_id' => $manager->id])
        ->assertStatus(409)
        ->assertJsonPath('message', 'You cannot change your own role.');

    $this->actingAs($first)
        ->patchJson("/api/rms/users/{$first->id}", ['name' => 'Carolina M.'])
        ->assertOk()
        ->assertJsonPath('name', 'Carolina M.');

    expect($second->fresh()?->status)->toBe(UserStatus::Active);
});

test('demoting a second admin succeeds while the actor remains admin', function (): void {
    $actor = adminUser(['name' => 'Carolina']);
    $other = adminUser(['name' => 'Second']);
    $manager = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();

    $this->actingAs($actor)
        ->patchJson("/api/rms/users/{$other->id}", ['role_id' => $manager->id])
        ->assertOk()
        ->assertJsonPath('role.slug', 'manager');
});

test('the last active admin cannot be disabled or demoted even when another admin is invited', function (): void {
    $carolina = adminUser(['name' => 'Carolina']);
    User::factory()->invited()->withRole(SystemRole::Admin)->create(['name' => 'Incoming']);
    $manager = Role::query()->where('slug', SystemRole::Manager->value)->firstOrFail();

    $this->actingAs($carolina)
        ->postJson("/api/rms/users/{$carolina->id}/disable")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This would leave no active admin.');

    $this->actingAs($carolina)
        ->patchJson("/api/rms/users/{$carolina->id}", ['role_id' => $manager->id])
        ->assertStatus(409)
        ->assertJsonPath('message', 'This would leave no active admin.');

    expect(ChangeHistory::query()->whereIn('event', ['user.disabled', 'user.role_changed'])->count())->toBe(0);
    expect($carolina->fresh()?->status)->toBe(UserStatus::Active);
    expect($carolina->fresh()?->role?->slug)->toBe('admin');
});
