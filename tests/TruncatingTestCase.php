<?php

declare(strict_types=1);

namespace Tests;

use App\Services\Inventory\ClaimService;
use App\Services\References\ReferenceService;
use App\Support\History\History;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConfiguresTestConfig;
use Tests\Support\Inventory\ClaimHolder;

abstract class TruncatingTestCase extends BaseTestCase
{
    use ConfiguresTestConfig;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestConfig();

        History::$baseTransactionLevel = DB::transactionLevel();
        ReferenceService::$baseTransactionLevel = DB::transactionLevel();
        ClaimService::$baseTransactionLevel = DB::transactionLevel();

        Relation::morphMap([
            'claim_holder' => ClaimHolder::class,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['mysql', 'mysql_lock'] as $name) {
            if (! is_array(config('database.connections.'.$name))) {
                continue;
            }

            try {
                $connection = DB::connection($name);

                while ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
            } catch (\Throwable) {
            }
        }

        DB::setDefaultConnection((string) config('database.default'));

        History::$baseTransactionLevel = 0;
        ReferenceService::$baseTransactionLevel = 0;
        ClaimService::$baseTransactionLevel = 0;

        parent::tearDown();
    }
}
