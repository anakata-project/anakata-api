<?php

declare(strict_types=1);

use App\Enums\ConfigKind;
use App\Models\RateVersion;
use App\Support\Config\Documents\RatesDocument;
use Tests\Support\Config\TestConfigDocument;
use Tests\Support\Config\TestConfigVersion;

test('a harness override binds the test document on this app instance', function (): void {
    registerTestConfig();

    expect(ConfigKind::Rates->modelClass())->toBe(TestConfigVersion::class);
    expect(ConfigKind::Rates->documentClass())->toBe(TestConfigDocument::class);
});

test('the following rates test resolves the production RateVersion binding', function (): void {
    expect(ConfigKind::Rates->modelClass())->toBe(RateVersion::class);
    expect(ConfigKind::Rates->documentClass())->toBe(RatesDocument::class);
})->depends('a harness override binds the test document on this app instance');
