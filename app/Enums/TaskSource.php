<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskSource: string
{
    case System = 'SYSTEM';
    case User = 'USER';
}
