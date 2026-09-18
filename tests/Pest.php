<?php

declare(strict_types=1);

use App\Support\SensitiveFields;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
| Feature tests boot the Laravel application and refresh the database.
| Unit tests stay on PHPUnit\Framework\TestCase and do not touch the database.
*/

pest()->extend(TestCase::class)->in('Feature');

function assertNoSensitiveFields(TestResponse $response): void
{
    $json = $response->json();

    expect(SensitiveFields::keysIn(is_array($json) ? $json : []))->toBeEmpty();
}
