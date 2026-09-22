<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Crm\OpenDealForCharterEnquiry;
use App\Events\CharterEnquiryReceived;
use App\Models\CharterEnquiry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class OpenDealOnCharterEnquiryReceived implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly OpenDealForCharterEnquiry $deals) {}

    public function handle(CharterEnquiryReceived $event): void
    {
        $enquiry = CharterEnquiry::query()->find($event->enquiry->id);

        if (! $enquiry instanceof CharterEnquiry) {
            return;
        }

        $this->deals->handle($enquiry);
    }
}
