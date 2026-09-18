<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| Feature tests boot the Laravel application and refresh the database.
| Unit tests stay on PHPUnit\Framework\TestCase and do not touch the database.
*/

pest()->extend(TestCase::class)->in('Feature');
