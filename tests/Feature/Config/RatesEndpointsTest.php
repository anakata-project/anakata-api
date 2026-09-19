<?php

declare(strict_types=1);

use App\Models\ChangeHistory;
use App\Models\RateVersion;
use App\Support\Config\Documents\RatesDocument;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('a sales exec can view rates and cannot publish', function (): void {
    $sales = salesExecUser();

    $this->actingAs($sales)
        ->getJson('/api/rms/rates')
        ->assertOk()
        ->assertJsonPath('version', 1)
        ->assertJsonPath('document.years.0.suite_pp', 13300)
        ->assertJsonPath('published_by', null);

    $document = ratesDocument();
    $document['years'][1]['suite_pp'] = 15000;

    $this->actingAs($sales)
        ->postJson('/api/rms/rates/versions', [
            'document' => $document,
            'base_version' => 1,
            'approval_reference' => 'BOARD-1',
        ])
        ->assertForbidden();

    expect(RateVersion::query()->count())->toBe(1);
});

test('an admin can publish a suite price change', function (): void {
    $admin = adminUser(['name' => 'Carolina M.']);
    $document = ratesDocument();
    $document['years'][1]['suite_pp'] = 15000;

    $this->actingAs($admin)
        ->postJson('/api/rms/rates/versions', [
            'document' => $document,
            'base_version' => 1,
            'approval_reference' => 'BOARD-22',
        ])
        ->assertCreated()
        ->assertJsonPath('version', 2)
        ->assertJsonPath('changes.0.label', 'Suite 2028')
        ->assertJsonPath('changes.0.from', 13965)
        ->assertJsonPath('changes.0.to', 15000)
        ->assertJsonPath('approval_reference', 'BOARD-22');

    $entry = ChangeHistory::query()->where('event', 'rates.published')->latest('id')->first();
    expect($entry)->not->toBeNull();
    expect($entry?->reason)->toBe('BOARD-22');
    expect($entry?->subject_type)->toBe('rate_version');
});

test('price check with an invalid document is a 422 keyed document.path', function (): void {
    $admin = adminUser();
    $document = ratesDocument(['currency' => 'EUR']);

    $this->actingAs($admin)
        ->postJson('/api/rms/rates/price-check', [
            'year' => 2027,
            'document' => $document,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['document.currency']);
});

test('price check against an identical 2027 document has zero differences', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->postJson('/api/rms/rates/price-check', [
            'year' => 2027,
            'document' => RatesDocument::initial(),
        ]);

    $response->assertOk();
    expect($response->json('scenarios'))->toHaveCount(8);

    foreach ($response->json('scenarios') as $scenario) {
        expect($scenario['difference'])->toBe(0);
        expect($scenario['published']['total'])->toBe($scenario['draft']['total']);
    }

    expect($response->json('scenarios.0.key'))->toBe('suite_2_adults');
    expect($response->json('scenarios.0.published.total'))->toBe(26600);
    expect($response->json('scenarios.0.published.deposit'))->toBe(2660);
});

test('get current rates is version 1 after the seeder', function (): void {
    $this->actingAs(adminUser())
        ->getJson('/api/rms/rates')
        ->assertOk()
        ->assertJsonPath('version', 1)
        ->assertJsonPath('document.currency', 'USD')
        ->assertJsonPath('document.years.0.year', 2027)
        ->assertJsonPath('approval_reference', ConfigSeeder::APPROVAL_REFERENCE);
});
