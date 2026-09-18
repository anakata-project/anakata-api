<?php

declare(strict_types=1);

namespace Tests;

use App\Support\History\History;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        History::$baseTransactionLevel = DB::transactionLevel();
    }

    protected function tearDown(): void
    {
        History::$baseTransactionLevel = 0;

        parent::tearDown();
    }
}
