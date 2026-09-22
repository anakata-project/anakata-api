<?php

declare(strict_types=1);

namespace App\Support\Manifests;

use App\Models\Guest;

/**
 * Dietary, emergency and medical lines on the captain's manifest.
 * Preferences sit beside the guest notes. A change is a passenger change
 * because these cells are part of the captain snapshot hash.
 */
final class CaptainParticulars
{
    /**
     * @return array{emergency: string, dietary: string, medical: string}
     */
    public static function for(Guest $guest): array
    {
        $preference = $guest->currentPreference;

        return [
            'emergency' => self::text($preference?->emergency_contact),
            'dietary' => self::text(self::join($preference?->answer('diet'), $guest->dietary_note)),
            'medical' => self::text(self::join(
                self::join($guest->medical_note, $guest->accessibility_note),
                $preference?->accessibility,
            )),
        ];
    }

    private static function join(?string $medical, ?string $accessibility): ?string
    {
        $parts = array_values(array_filter(
            [$medical, $accessibility],
            fn (?string $value): bool => self::text($value) !== '—',
        ));

        if ($parts === []) {
            return null;
        }

        return implode(' · ', array_map(
            fn (?string $value): string => trim((string) $value),
            $parts,
        ));
    }

    private static function text(?string $value): string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? '—' : $trimmed;
    }
}
