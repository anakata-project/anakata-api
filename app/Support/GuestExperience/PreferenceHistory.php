<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

use App\Models\Guest;
use App\Models\GuestPreference;
use App\Support\Iso;

final class PreferenceHistory
{
    /**
     * @return array{current: array<string, mixed>|null, versions: list<array<string, mixed>>}
     */
    public static function forGuest(Guest $guest, bool $sensitive): array
    {
        $versions = $guest->preferences()
            ->with('recordedBy')
            ->orderBy('version')
            ->get();

        $current = $versions->whereNull('purged_at')->sortByDesc('version')->first();

        return [
            'current' => $current instanceof GuestPreference ? self::version($current, $sensitive) : null,
            'versions' => $versions->map(fn (GuestPreference $preference): array => self::version($preference, $sensitive))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function version(GuestPreference $preference, bool $sensitive): array
    {
        $row = [
            'id' => $preference->id,
            'version' => $preference->version,
            'source' => $preference->source->value,
            'answered_at' => Iso::utc($preference->answered_at),
            'purged_at' => Iso::utc($preference->purged_at),
            'recorded_by' => $preference->recorded_by === null ? null : [
                'id' => $preference->recorded_by,
                'name' => $preference->recordedBy?->name,
            ],
            'answers' => self::openAnswers($preference),
            'accessibility_provided' => trim((string) $preference->accessibility) !== '',
            'emergency_contact_provided' => trim((string) $preference->emergency_contact) !== '',
        ];

        if ($sensitive) {
            $row['accessibility'] = self::blank($preference->accessibility);
            $row['emergency_contact'] = self::blank($preference->emergency_contact);
        }

        return $row;
    }

    /**
     * @return array<string, string>
     */
    private static function openAnswers(GuestPreference $preference): array
    {
        $answers = [];

        foreach (PreferenceQuestions::all() as $question) {
            if ($question->restricted) {
                continue;
            }

            $answers[$question->key] = $preference->answer($question->key) ?? '';
        }

        return $answers;
    }

    private static function blank(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
