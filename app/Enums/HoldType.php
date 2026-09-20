<?php

declare(strict_types=1);

namespace App\Enums;

enum HoldType: string
{
    case Web = 'WEB';
    case Request = 'REQUEST';
    case Agency = 'AGENCY';
    case CharterQuote = 'CHARTER_QUOTE';
}
