<?php

declare(strict_types=1);

namespace App\Services\Pricing;

enum CabinCategory: string
{
    case Suite = 'SUITE';
    case Owner = 'OWNER';
}
