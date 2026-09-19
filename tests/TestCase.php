<?php

declare(strict_types=1);

namespace Tests;

use App\Services\References\ReferenceService;
use App\Support\History\History;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConfiguresTestConfig;

abstract class TestCase extends BaseTestCase
{
    use ConfiguresTestConfig;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestConfig();

        History::$baseTransactionLevel = DB::transactionLevel();
        ReferenceService::$baseTransactionLevel = DB::transactionLevel();
    }

    protected function tearDown(): void
    {
        History::$baseTransactionLevel = 0;
        ReferenceService::$baseTransactionLevel = 0;

        parent::tearDown();
    }
}
