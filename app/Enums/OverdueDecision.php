<?php

declare(strict_types=1);

namespace App\Enums;

enum OverdueDecision: string
{
    case Extend = 'EXTEND';
    case Cancel = 'CANCEL';
}
