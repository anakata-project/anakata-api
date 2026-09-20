<?php

declare(strict_types=1);

namespace App\Enums;

enum PreferredChannel: string
{
    case Email = 'EMAIL';
    case Whatsapp = 'WHATSAPP';
    case Phone = 'PHONE';
}
