<?php

declare(strict_types=1);

namespace App\Enums;

enum PreferenceStatus: string
{
    case Answered = 'ANSWERED';
    case SentNoReply = 'SENT_NO_REPLY';
    case Scheduled = 'SCHEDULED';

    public function label(): string
    {
        return match ($this) {
            self::Answered => 'ANSWERED',
            self::SentNoReply => 'SENT — NO REPLY',
            self::Scheduled => 'SCHEDULED',
        };
    }
}
