<?php

declare(strict_types=1);

namespace App\Enums;

enum ManifestKind: string
{
    case Dpng = 'DPNG';
    case Captain = 'CAPTAIN';

    public function label(): string
    {
        return match ($this) {
            self::Dpng => 'DPNG passenger list',
            self::Captain => 'Captain\'s manifest',
        };
    }
}
