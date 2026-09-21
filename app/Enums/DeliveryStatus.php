<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryStatus: string
{
    case Queued = 'QUEUED';
    case Sent = 'SENT';
    case Failed = 'FAILED';
    case Blocked = 'BLOCKED';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Blocked => 'Blocked',
        };
    }
}
