<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Agency;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class AgencyApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Agency $agency) {}
}
