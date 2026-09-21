<?php

declare(strict_types=1);

namespace App\Support\Engine;

final class PagePath
{
    public const COMPLETE_STORED = '/complete/[token]';

    /** @var array<string, string> */
    private const TOKEN_PREFIXES = [
        '/complete/' => self::COMPLETE_STORED,
    ];

    public static function redact(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $trimmed = trim($path);

        if ($trimmed === '') {
            return null;
        }

        $withoutQuery = explode('?', $trimmed, 2)[0];
        $withoutHash = explode('#', $withoutQuery, 2)[0];

        if ($withoutHash === '' || ! str_starts_with($withoutHash, '/')) {
            return $withoutHash === '' ? null : $withoutHash;
        }

        foreach (self::TOKEN_PREFIXES as $prefix => $stored) {
            if (str_starts_with($withoutHash, $prefix)) {
                return $stored;
            }
        }

        return $withoutHash;
    }
}
