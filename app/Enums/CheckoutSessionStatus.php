<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckoutSessionStatus: string
{
    case Holding = 'HOLDING';
    case Submitted = 'SUBMITTED';
    case Released = 'RELEASED';
    case Expired = 'EXPIRED';
}
