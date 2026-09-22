<?php

declare(strict_types=1);

namespace App\Actions\GuestExperience;

use App\Actions\Action;
use App\Enums\PreferenceSource;
use App\Models\Guest;
use App\Models\GuestPreference;
use App\Models\User;
use App\Support\GuestExperience\PreferenceQuestions;
use App\Support\History\History;
use Illuminate\Auth\Access\AuthorizationException;

final class RecordGuestPreferences extends Action
{
    /**
     * Present keys only. An empty string was sent blank; an omitted key is absent.
     * Restricted answers: a guest link cannot clear them (blank or omitted copies the
     * previous value). Staff with guests.view_sensitive clear by sending the key empty.
     *
     * @param  array<string, string>  $answers
     */
    public function handle(
        Guest $guest,
        array $answers,
        PreferenceSource $source,
        bool $canWriteRestricted,
        ?User $actor = null,
        ?string $actorLabel = null,
    ): GuestPreference {
        /** @var GuestPreference $preference */
        $preference = $this->transaction(function () use ($guest, $answers, $source, $canWriteRestricted, $actor, $actorLabel): GuestPreference {
            Guest::query()->whereKey($guest->id)->lockForUpdate()->first();

            $previous = GuestPreference::query()
                ->where('guest_id', $guest->id)
                ->current()
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            $latestVersion = (int) GuestPreference::query()
                ->where('guest_id', $guest->id)
                ->max('version');

            $open = [];
            $accessibility = $this->restricted(
                'access',
                $answers,
                $previous?->accessibility,
                $source,
                $canWriteRestricted,
            );
            $emergency = $this->restricted(
                'emerg',
                $answers,
                $previous?->emergency_contact,
                $source,
                $canWriteRestricted,
            );

            foreach (PreferenceQuestions::all() as $question) {
                if ($question->restricted) {
                    continue;
                }

                $value = trim($answers[$question->key] ?? '');

                if ($value !== '') {
                    $open[$question->key] = $value;
                }
            }

            $preference = GuestPreference::query()->create([
                'guest_id' => $guest->id,
                'version' => $latestVersion + 1,
                'answers' => $open,
                'accessibility' => $accessibility,
                'emergency_contact' => $emergency,
                'source' => $source,
                'recorded_by' => $source === PreferenceSource::Staff ? $actor?->id : null,
                'answered_at' => now(),
            ]);

            $changed = $this->changedKeys($previous, $preference);

            if ($changed !== []) {
                History::record(
                    $guest,
                    'guest.preferences_recorded',
                    after: [
                        'version' => $preference->version,
                        'source' => $source->value,
                        'keys' => $changed,
                    ],
                    actor: $source === PreferenceSource::Staff ? $actor : null,
                    actorLabel: $source === PreferenceSource::GuestLink ? $actorLabel : null,
                );
            }

            return $preference;
        });

        return $preference;
    }

    /**
     * @param  array<string, string>  $answers
     */
    private function restricted(
        string $key,
        array $answers,
        ?string $previous,
        PreferenceSource $source,
        bool $canWriteRestricted,
    ): ?string {
        $present = array_key_exists($key, $answers);
        $value = $present ? trim($answers[$key]) : '';

        if ($source === PreferenceSource::GuestLink) {
            if (! $present || $value === '') {
                return $this->blankToNull($previous);
            }

            return $value;
        }

        if (! $canWriteRestricted && $present) {
            throw new AuthorizationException('Restricted answers need guests.view_sensitive.');
        }

        if (! $present) {
            return $this->blankToNull($previous);
        }

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function changedKeys(?GuestPreference $previous, GuestPreference $next): array
    {
        $changed = [];

        foreach (PreferenceQuestions::all() as $question) {
            $before = $previous === null ? '' : $this->stored($previous, $question->key, $question->restricted);
            $after = $this->stored($next, $question->key, $question->restricted);

            if ($before !== $after) {
                $changed[] = $question->key;
            }
        }

        return $changed;
    }

    private function stored(GuestPreference $preference, string $key, bool $restricted): string
    {
        if ($restricted) {
            $value = $key === 'access' ? $preference->accessibility : $preference->emergency_contact;

            return trim((string) $value);
        }

        return trim((string) ($preference->answer($key) ?? ''));
    }

    private function blankToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
