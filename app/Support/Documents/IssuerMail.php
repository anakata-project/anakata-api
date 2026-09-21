<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Services\Config\CurrentConfig;

final class IssuerMail
{
    public static function replyTo(): string
    {
        return app(CurrentConfig::class)->businessRules()->legalEntity->email;
    }
}
