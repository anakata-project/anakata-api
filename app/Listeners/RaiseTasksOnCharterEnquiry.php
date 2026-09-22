<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CharterEnquiryReceived;
use App\Models\CharterEnquiry;
use App\Support\Crm\TaskSweep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RaiseTasksOnCharterEnquiry implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly TaskSweep $tasks) {}

    public function handle(CharterEnquiryReceived $event): void
    {
        $enquiry = CharterEnquiry::query()->find($event->enquiry->id);

        if ($enquiry instanceof CharterEnquiry) {
            $this->tasks->onCharterEnquiry($enquiry);
        }
    }
}
