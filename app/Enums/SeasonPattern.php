<?php

declare(strict_types=1);

namespace App\Enums;

enum SeasonPattern: string
{
    case Alt = 'ALT';
    case West = 'WEST';
    case North = 'NORTH';
}
