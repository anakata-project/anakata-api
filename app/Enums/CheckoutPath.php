<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckoutPath: string
{
    case PayLater = 'PAY_LATER';
    case PayDeposit = 'PAY_DEPOSIT';
}
