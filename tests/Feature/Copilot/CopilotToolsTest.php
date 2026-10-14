<?php

declare(strict_types=1);

use App\Ai\Tools\CreateContact;
use App\Ai\Tools\FetchObjects;
use App\Enums\Permission;
use App\Models\Contact;
use App\Models\Role;
use App\Models\User;
use Laravel\Ai\Tools\Request;

it('creates a contact for a user who may manage contacts', function (): void {
    $manager = managerUser();

    $json = (new CreateContact($manager))->handle(new Request([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]));

    $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['success'])->toBeTrue()
        ->and(Contact::query()->where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('refuses to create a contact without the permission', function (): void {
    $role = Role::factory()->create([
        'permissions' => [Permission::CopilotUse->value, Permission::PanelCrm->value],
    ]);
    $user = User::factory()->create(['role_id' => $role->id]);

    $json = (new CreateContact($user))->handle(new Request([
        'name' => 'No Permission',
    ]));

    $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['success'])->toBeFalse()
        ->and(Contact::query()->where('name', 'No Permission')->exists())->toBeFalse();
});

it('returns contacts the user may see and omits the unsubscribe token', function (): void {
    $manager = managerUser();
    $contact = Contact::factory()->create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);

    $json = (new FetchObjects($manager))->handle(new Request([
        'object_type' => 'contact',
        'search' => 'Grace Hopper',
    ]));

    $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['success'])->toBeTrue()
        ->and($payload['results'][0]['id'])->toBe($contact->id)
        ->and($payload['results'][0])->not->toHaveKey('unsubscribe_token')
        ->and($payload['results'][0])->not->toHaveKey('dob');
});

it('refuses rms records to a crm-only user', function (): void {
    $role = Role::factory()->create([
        'permissions' => [Permission::CopilotUse->value, Permission::PanelCrm->value],
    ]);
    $user = User::factory()->create(['role_id' => $role->id]);

    $json = (new FetchObjects($user))->handle(new Request([
        'object_type' => 'booking',
    ]));

    $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['success'])->toBeFalse();
});
