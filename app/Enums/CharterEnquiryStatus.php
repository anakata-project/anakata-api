<?php

declare(strict_types=1);

namespace App\Enums;

enum CharterEnquiryStatus: string
{
    case New = 'NEW';
    case Contacted = 'CONTACTED';
    case Quoted = 'QUOTED';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Closed = 'CLOSED';
}
