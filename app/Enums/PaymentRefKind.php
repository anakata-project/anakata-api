<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentRefKind: string
{
    case Deposit = 'D';
    case Balance = 'B';
    case Refund = 'R';
    case Extras = 'X';
    case Other = 'O';
}
