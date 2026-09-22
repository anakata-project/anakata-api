<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CharterEnquiry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CharterEnquiryReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public CharterEnquiry $enquiry) {}
}
