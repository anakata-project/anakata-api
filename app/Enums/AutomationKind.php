<?php

declare(strict_types=1);

namespace App\Enums;

enum AutomationKind: string
{
    case Marketing = 'MARKETING';
    case Transactional = 'TRANSACTIONAL';
}
