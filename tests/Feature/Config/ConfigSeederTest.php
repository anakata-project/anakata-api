<?php

declare(strict_types=1);

use App\Enums\ConfigKind;
use App\Services\Config\CurrentConfig;
use Database\Seeders\ConfigSeeder;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\Config\TestConfigVersion;

test('the seeder is idempotent', function (): void {
    registerTestConfig(testConfigDocument());

    $this->seed(ConfigSeeder::class);
    $this->seed(ConfigSeeder::class);

    expect(TestConfigVersion::query()->count())->toBe(1);

    $row = TestConfigVersion::query()->firstOrFail();
    expect($row->version)->toBe(1);
    expect($row->changes)->toBe([]);
    expect($row->approval_reference)->toBe(ConfigSeeder::APPROVAL_REFERENCE);
    expect($row->created_by)->toBeNull();
    expect($row->asDocument()->toArray()['terms']['cabin_deposit_pct'])->toBe(10);
});

test('the seeder refuses an invalid initial document', function (): void {
    registerTestConfig(testConfigDocument(['terms' => ['cabin_deposit_pct' => 200]]));

    expect(fn () => $this->seed(ConfigSeeder::class))
        ->toThrow(ValidationException::class);

    expect(TestConfigVersion::query()->count())->toBe(0);
    expect(fn () => app(CurrentConfig::class)->version(ConfigKind::Rates))
        ->toThrow(RuntimeException::class, 'No published rates — run the seeders');
});
