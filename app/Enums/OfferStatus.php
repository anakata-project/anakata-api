<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferStatus: string
{
    case Draft = 'DRAFT';
    case Pending = 'PENDING';
    case Live = 'LIVE';
    case Paused = 'PAUSED';

    public const DerivedExpired = 'EXPIRED';
}
