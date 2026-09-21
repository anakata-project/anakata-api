<?php

declare(strict_types=1);

use App\Models\ChangeHistory;
use App\Models\ExtraVersion;
use App\Support\Config\Documents\ExtrasDocument;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('a sales exec can view extras and cannot publish', function (): void {
    $sales = salesExecUser();

    $this->actingAs($sales)
        ->getJson('/api/rms/extras')
        ->assertOk()
        ->assertJsonPath('version', 1)
        ->assertJsonPath('document.items.0.code', 'FLT')
        ->assertJsonPath('document.items.0.price_usd', 420)
        ->assertJsonPath('published_by', null);

    $document = extrasDocument();
    $document['items'][0]['price_usd'] = 450;

    $this->actingAs($sales)
        ->postJson('/api/rms/extras/versions', [
            'document' => $document,
            'base_version' => 1,
            'approval_reference' => 'BOARD-1',
        ])
        ->assertForbidden();

    expect(ExtraVersion::query()->count())->toBe(1);
});

test('an admin can publish a price change', function (): void {
    $admin = adminUser(['name' => 'Carolina M.']);
    $document = extrasDocument();
    $document['items'][0]['price_usd'] = 450;

    $this->actingAs($admin)
        ->postJson('/api/rms/extras/versions', [
            'document' => $document,
            'base_version' => 1,
            'approval_reference' => 'BOARD-22',
        ])
        ->assertCreated()
        ->assertJsonPath('version', 2)
        ->assertJsonPath('document.items.0.price_usd', 450)
        ->assertJsonPath('approval_reference', 'BOARD-22');

    $entry = ChangeHistory::query()->where('event', 'extras.published')->latest('id')->first();
    expect($entry)->not->toBeNull();
    expect($entry?->reason)->toBe('BOARD-22');
    expect($entry?->subject_type)->toBe('extra_version');
});

test('a stale base version is 409 and an approval reference is required', function (): void {
    $admin = adminUser();
    $document = extrasDocument();
    $document['items'][0]['price_usd'] = 450;

    $this->actingAs($admin)
        ->postJson('/api/rms/extras/versions', [
            'document' => $document,
            'base_version' => 0,
            'approval_reference' => 'BOARD-1',
        ])
        ->assertStatus(409);

    $this->actingAs($admin)
        ->postJson('/api/rms/extras/versions', [
            'document' => $document,
            'base_version' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['approval_reference']);
});

test('dropping or renaming a published code is 422 and a name change is allowed', function (): void {
    $admin = adminUser();

    $dropped = extrasDocument();
    array_shift($dropped['items']);

    $this->actingAs($admin)
        ->postJson('/api/rms/extras/versions', [
            'document' => $dropped,
            'base_version' => 1,
            'approval_reference' => 'BOARD-1',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['document.items.0.code']);

    $renamed = extrasDocument();
    $renamed['items'][0]['code'] = 'FLX';

    $this->actingAs($admin)
        ->postJson('/api/rms/extras/versions', [
            'document' => $renamed,
            'base_version' => 1,
            'approval_reference' => 'BOARD-1',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['document.items.0.code']);

    $name = extrasDocument();
    $name['items'][0]['name'] = 'Domestic flights (updated)';
    $name['items'][3]['active'] = false;

    $this->actingAs($admin)
        ->postJson('/api/rms/extras/versions', [
            'document' => $name,
            'base_version' => 1,
            'approval_reference' => 'BOARD-1',
        ])
        ->assertCreated()
        ->assertJsonPath('version', 2)
        ->assertJsonPath('document.items.0.name', 'Domestic flights (updated)')
        ->assertJsonPath('document.items.3.active', false);
});

test('validate accepts the initial document', function (): void {
    $this->actingAs(adminUser())
        ->postJson('/api/rms/extras/validate', [
            'document' => ExtrasDocument::initial(),
        ])
        ->assertOk()
        ->assertJsonPath('errors', []);
});

test('get current extras is version 1 after the seeder', function (): void {
    $this->actingAs(adminUser())
        ->getJson('/api/rms/extras')
        ->assertOk()
        ->assertJsonPath('version', 1)
        ->assertJsonPath('document.items.0.code', 'FLT')
        ->assertJsonPath('approval_reference', ConfigSeeder::APPROVAL_REFERENCE);
});
