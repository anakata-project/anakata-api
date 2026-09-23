<?php

declare(strict_types=1);

namespace App\Support\Engine;

final class PagePath
{
    public const COMPLETE_STORED = '/complete/[token]';

    public const QUESTIONNAIRE_STORED = '/questionnaire/[token]';

    public const SURVEY_STORED = '/survey/[token]';

    public const CHARTER_PROPOSAL_STORED = '/charter-proposal/[token]';

    /** @var array<string, string> */
    private const TOKEN_PREFIXES = [
        '/complete/' => self::COMPLETE_STORED,
        '/questionnaire/' => self::QUESTIONNAIRE_STORED,
        '/survey/' => self::SURVEY_STORED,
        '/charter-proposal/' => self::CHARTER_PROPOSAL_STORED,
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
