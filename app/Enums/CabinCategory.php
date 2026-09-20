<?php

declare(strict_types=1);

namespace App\Enums;

enum CabinCategory: string
{
    case Suite = 'SUITE';
    case Owner = 'OWNER';
}
