<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\RefundRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class RefundRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public RefundRequest $refundRequest) {}
}
