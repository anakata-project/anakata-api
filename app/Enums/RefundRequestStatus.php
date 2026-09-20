<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundRequestStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Executed = 'EXECUTED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Executed => 'Executed',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }
}
