<?php

declare(strict_types=1);

use App\Enums\ConfigKind;
use App\Models\ChangeHistory;
use App\Services\Config\ConfigPublisher;
use Database\Seeders\RolesSeeder;
use Tests\Support\Config\TestConfigVersion;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    registerTestConfig();
});

test('validate returns errors warnings and changes without writing', function (): void {
    $admin = adminUser();

    app(ConfigPublisher::class)->publish(
        ConfigKind::Rates,
        testConfigDocument(),
        0,
        'A',
        $admin,
    );

    $response = $this->actingAs($admin)->postJson('/api/rms/test-config/validate', [
        'document' => testConfigDocument([
            'terms' => ['cabin_deposit_pct' => 200],
            'title' => 'Moved',
        ]),
    ]);

    $response->assertOk();
    expect($response->json('errors'))->toHaveKey('terms.cabin_deposit_pct');
    expect($response->json('changes'))->toBe([]);
    expect(TestConfigVersion::query()->count())->toBe(1);

    $ok = $this->actingAs($admin)->postJson('/api/rms/test-config/validate', [
        'document' => testConfigDocument([
            'terms' => ['cabin_deposit_pct' => 75],
            'title' => 'Moved',
        ]),
    ]);

    $ok->assertOk();
    expect($ok->json('errors'))->toBe([]);
    expect($ok->json('warnings'))->toBe([
        [
            'path' => 'terms.cabin_deposit_pct',
            'message' => 'Cabin deposit % is unusually high.',
        ],
    ]);
    expect($ok->json('changes'))->toHaveCount(2);
    expect(TestConfigVersion::query()->count())->toBe(1);
    expect(ChangeHistory::query()->where('event', 'rates.published')->count())->toBe(1);
});

test('a sales exec can read config and cannot publish', function (): void {
    $admin = adminUser();
    app(ConfigPublisher::class)->publish(
        ConfigKind::Rates,
        testConfigDocument(),
        0,
        'A',
        $admin,
    );

    $sales = salesExecUser();

    $this->actingAs($sales)
        ->getJson('/api/rms/test-config')
        ->assertOk()
        ->assertJsonPath('version', 1)
        ->assertJsonPath('document.title', 'Cabin terms')
        ->assertJsonPath('published_by.id', $admin->id);

    $this->actingAs($sales)
        ->postJson('/api/rms/test-config/versions', [
            'document' => testConfigDocument(['title' => 'Nope']),
            'base_version' => 1,
            'approval_reference' => 'X',
        ])
        ->assertForbidden();

    expect(TestConfigVersion::query()->count())->toBe(1);
});

test('an admin can publish through the http endpoint', function (): void {
    $admin = adminUser();
    app(ConfigPublisher::class)->publish(
        ConfigKind::Rates,
        testConfigDocument(),
        0,
        'A',
        $admin,
    );

    $this->actingAs($admin)
        ->postJson('/api/rms/test-config/versions', [
            'document' => testConfigDocument(['title' => 'Published via HTTP']),
            'base_version' => 1,
            'approval_reference' => 'BOARD-9',
        ])
        ->assertCreated()
        ->assertJsonPath('version', 2)
        ->assertJsonPath('document.title', 'Published via HTTP')
        ->assertJsonPath('approval_reference', 'BOARD-9');

    $this->actingAs($admin)
        ->getJson('/api/rms/test-config/versions')
        ->assertOk()
        ->assertJsonPath('data.0.version', 2)
        ->assertJsonPath('data.1.version', 1);

    $this->actingAs($admin)
        ->getJson('/api/rms/test-config/versions/2')
        ->assertOk()
        ->assertJsonPath('document.title', 'Published via HTTP');
});
