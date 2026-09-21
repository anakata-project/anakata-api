<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Support\Engine\PagePath;
use Carbon\CarbonImmutable;

final class AttributionTouch
{
    /** @var list<string> */
    private const KEYS = [
        'source',
        'medium',
        'campaign',
        'content',
        'term',
        'landing_path',
        'captured_at',
    ];

    /**
     * @return array<string, string>|null
     */
    public static function from(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $touch = [];

        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed === '') {
                continue;
            }

            if ($key === 'landing_path') {
                $redacted = PagePath::redact($trimmed);

                if ($redacted === null || ! str_starts_with($redacted, '/') || strlen($redacted) > 200) {
                    continue;
                }

                $touch[$key] = $redacted;

                continue;
            }

            if ($key === 'captured_at') {
                try {
                    $touch[$key] = CarbonImmutable::parse($trimmed)->utc()->toIso8601String();
                } catch (\Throwable) {
                    continue;
                }

                continue;
            }

            $touch[$key] = mb_substr($trimmed, 0, 100);
        }

        return $touch === [] ? null : $touch;
    }
}
