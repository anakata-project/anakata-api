<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSetupTest extends TestCase
{
    public function test_default_connection_is_mysql_anakata_test(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        $this->assertSame(
            'mysql',
            $driver,
            "Tests must use the mysql driver on the default connection, got [{$driver}]. Check phpunit.xml (DB_CONNECTION=mysql).",
        );

        $this->assertSame(
            'anakata_test',
            $database,
            "Tests must use the anakata_test database, got [{$database}]. Check phpunit.xml (DB_DATABASE=anakata_test) so tests never hit the app database.",
        );
    }

    public function test_core_tables_exist_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('users'), 'users table missing after migration.');
        $this->assertTrue(Schema::hasTable('sessions'), 'sessions table missing after migration.');
        $this->assertTrue(Schema::hasTable('cache'), 'cache table missing after migration.');
        $this->assertTrue(Schema::hasTable('jobs'), 'jobs table missing after migration.');
    }
}
