<?php

declare(strict_types=1);

namespace App\Enums;

enum SubjectRequestType: string
{
    case Access = 'ACCESS';
    case Erasure = 'ERASURE';
    case Rectification = 'RECTIFICATION';
    case Objection = 'OBJECTION';

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Access',
            self::Erasure => 'Erasure',
            self::Rectification => 'Rectification',
            self::Objection => 'Objection',
        };
    }
}
