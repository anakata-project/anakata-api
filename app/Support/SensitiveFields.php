<?php

declare(strict_types=1);

namespace App\Support;

final class SensitiveFields
{
    /** @var list<string> */
    private const NAMES = [
        'passport_no',
        'medical_note',
        'dietary_note',
        'accessibility_note',
        'dob',
        'nationality',
        'accessibility',
        'emergency_contact',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::NAMES;
    }

    /**
     * @return list<string>
     */
    public static function keysIn(mixed $payload): array
    {
        $found = [];
        self::collect($payload, $found);

        return array_values(array_unique($found));
    }

    public static function strip(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        $stripped = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array($key, self::NAMES, true)) {
                continue;
            }

            $stripped[$key] = self::strip($value);
        }

        return $stripped;
    }

    /**
     * @param  list<string>  $found
     */
    private static function collect(mixed $payload, array &$found): void
    {
        if (! is_array($payload)) {
            return;
        }

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array($key, self::NAMES, true)) {
                $found[] = $key;
            }

            self::collect($value, $found);
        }
    }
}
