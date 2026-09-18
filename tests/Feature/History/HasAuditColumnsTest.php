<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('creating without a user leaves audit columns null', function (): void {
    $role = Role::factory()->create();

    expect($role->created_by)->toBeNull();
    expect($role->updated_by)->toBeNull();
});

test('creating as a user fills created_by and updated_by', function (): void {
    $actor = User::factory()->create();

    $this->actingAs($actor);

    $role = Role::factory()->create();

    expect($role->created_by)->toBe($actor->id);
    expect($role->updated_by)->toBe($actor->id);
});

test('user A creates and user B updates so updated_by is B', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA);
    $role = Role::factory()->create(['description' => 'first']);

    expect($role->created_by)->toBe($userA->id);
    expect($role->updated_by)->toBe($userA->id);

    $this->actingAs($userB);
    $role->update(['description' => 'second']);

    $role->refresh();

    expect($role->created_by)->toBe($userA->id);
    expect($role->updated_by)->toBe($userB->id);
});

test('an update with no user sets updated_by to null', function (): void {
    $userA = User::factory()->create();

    $this->actingAs($userA);
    $role = Role::factory()->create(['description' => 'first']);

    Auth::forgetGuards();

    $role->update(['description' => 'system']);

    expect($role->fresh()->updated_by)->toBeNull();
});

test('an explicitly dirty updated_by is not overwritten', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();

    $this->actingAs($userA);
    $role = Role::factory()->create();

    $this->actingAs($userB);
    $role->updated_by = $userC->id;
    $role->description = 'kept';
    $role->save();

    expect($role->fresh()->updated_by)->toBe($userC->id);
});
