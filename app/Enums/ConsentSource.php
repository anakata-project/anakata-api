<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsentSource: string
{
    case Engine = 'ENGINE';
    case PaymentLink = 'PAYMENT_LINK';
    case Staff = 'STAFF';
}
