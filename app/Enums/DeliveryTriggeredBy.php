<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryTriggeredBy: string
{
    case System = 'SYSTEM';
    case User = 'USER';

    public function label(): string
    {
        return match ($this) {
            self::System => 'System',
            self::User => 'User',
        };
    }
}
