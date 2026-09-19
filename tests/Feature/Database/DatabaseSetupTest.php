<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('default connection is mysql anakata_test', function (): void {
    $connection = config('database.default');
    $driver = config("database.connections.{$connection}.driver");
    $database = config("database.connections.{$connection}.database");

    expect($driver)->toBe('mysql', "Tests must use the mysql driver on the default connection, got [{$driver}]. Check phpunit.xml (DB_CONNECTION=mysql).");
    expect($database)->toBe('anakata_test', "Tests must use the anakata_test database, got [{$database}]. Check phpunit.xml (DB_DATABASE=anakata_test) so tests never hit the app database.");
});

test('core tables exist after migration', function (): void {
    expect(Schema::hasTable('users'))->toBeTrue('users table missing after migration.');
    expect(Schema::hasTable('sessions'))->toBeTrue('sessions table missing after migration.');
    expect(Schema::hasTable('cache'))->toBeTrue('cache table missing after migration.');
    expect(Schema::hasTable('jobs'))->toBeTrue('jobs table missing after migration.');
    expect(Schema::hasTable('test_config_versions'))->toBeTrue(
        'test_config_versions missing — tests/database/migrations must be registered for the whole suite.',
    );
    expect(Schema::hasTable('rate_versions'))->toBeTrue('rate_versions table missing after migration.');
    expect(Schema::hasTable('engine_settings_versions'))->toBeTrue('engine_settings_versions table missing after migration.');
});
