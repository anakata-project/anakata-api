<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsentPurpose: string
{
    case Marketing = 'MARKETING';
    case Profiling = 'PROFILING';
    case Remarketing = 'REMARKETING';
    case Whatsapp = 'WHATSAPP';
    case Analytics = 'ANALYTICS';

    public function label(): string
    {
        return match ($this) {
            self::Marketing => 'Marketing',
            self::Profiling => 'Profiling',
            self::Remarketing => 'Remarketing',
            self::Whatsapp => 'WhatsApp',
            self::Analytics => 'Analytics',
        };
    }
}
