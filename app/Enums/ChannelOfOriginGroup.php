<?php

declare(strict_types=1);

namespace App\Enums;

enum ChannelOfOriginGroup: string
{
    case Direct = 'Direct';
    case Marketing = 'Marketing';
    case TradeCorporateGroups = 'Trade, corporate & groups';
    case DistributionPartnersOther = 'Distribution, partners & other';
}
