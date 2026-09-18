<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\ChecksOwnRecords;

abstract class Policy
{
    use ChecksOwnRecords;
}
