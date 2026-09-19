<?php

declare(strict_types=1);

namespace App\Enums;

enum RuleStatus: string
{
    case Confirmed = 'CONFIRMED';
    case PendingClient = 'PENDING_CLIENT';
    case PendingLegal = 'PENDING_LEGAL';
    case TextInDrafting = 'TEXT_IN_DRAFTING';
    case RmsSpec = 'RMS_SPEC';

    public function isPending(): bool
    {
        return match ($this) {
            self::PendingClient, self::PendingLegal, self::TextInDrafting => true,
            default => false,
        };
    }
}
