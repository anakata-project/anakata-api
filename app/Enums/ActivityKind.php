<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityKind: string
{
    case Call = 'CALL';
    case Email = 'EMAIL';
    case Meeting = 'MEETING';
    case Note = 'NOTE';
    case TaskCompleted = 'TASK_COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Email => 'Email',
            self::Meeting => 'Meeting',
            self::Note => 'Note',
            self::TaskCompleted => 'Task completed',
        };
    }
}
