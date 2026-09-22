<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\RefundRequested;
use App\Models\RefundRequest;
use App\Support\Crm\TaskSweep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RaiseTasksOnRefundRequested implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly TaskSweep $tasks) {}

    public function handle(RefundRequested $event): void
    {
        $request = RefundRequest::query()->find($event->refundRequest->id);

        if ($request instanceof RefundRequest) {
            $this->tasks->onRefundRequested($request);
        }
    }
}
