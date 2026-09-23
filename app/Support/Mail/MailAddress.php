<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\Contact;

final class MailAddress
{
    public static function extract(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match('/<([^>]+)>/', $raw, $matches) === 1) {
            $raw = $matches[1];
        }

        return Contact::normalizeEmail($raw) ?? '';
    }
}
