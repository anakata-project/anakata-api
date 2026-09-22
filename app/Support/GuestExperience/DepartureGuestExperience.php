<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

use App\Enums\PreferenceSource;
use App\Enums\PreferenceStatus;
use App\Models\Departure;
use App\Models\Guest;
use App\Models\GuestPreference;
use App\Services\Config\CurrentConfig;
use App\Support\BusinessTime;
use App\Support\Iso;
use App\Support\Manifests\ManifestRoster;

final class DepartureGuestExperience
{
    public function __construct(private readonly CurrentConfig $config) {}

    /**
     * @return array{
     *     send_date: string,
     *     send_state: 'sent'|'scheduled',
     *     kpis: array{guests: int, bookings: int, answered: int, total: int, celebrations: int, accessibility_or_medical: int},
     *     guests: list<array{
     *         guest_id: int,
     *         name: string,
     *         booking_reference: string,
     *         email: string|null,
     *         email_note: string|null,
     *         cabin: string,
     *         status: PreferenceStatus,
     *         status_label: string,
     *         answered_at: string|null,
     *         source: PreferenceSource|null,
     *         send_date: string,
     *         dietary: string|null,
     *         celebration: string|null,
     *         activity: string|null,
     *         accessibility_provided: bool,
     *         emergency_contact_provided: bool,
     *         accessibility?: string|null,
     *         emergency_contact?: string|null
     *     }>
     * }
     */
    public function forDeparture(Departure $departure, bool $sensitive): array
    {
        $days = $this->config->businessRules()->documents->pretripDaysBefore;
        $sendDate = BusinessTime::calendarDay($departure->date->toDateString())->subDays($days)->toDateString();
        $today = BusinessTime::now()->toDateString();
        $passengers = ManifestRoster::passengers($departure);
        $answered = 0;
        $celebrations = 0;
        $restricted = 0;
        $rows = [];

        foreach ($passengers as $passenger) {
            $guest = $passenger->guest;
            $preference = $guest->currentPreference;
            $status = $this->status($preference, $sendDate, $today);

            if ($status === PreferenceStatus::Answered) {
                $answered++;
            }

            if ($preference?->answer('celebr') !== null) {
                $celebrations++;
            }

            if (trim((string) $preference?->accessibility) !== '' || trim((string) $guest->medical_note) !== '') {
                $restricted++;
            }

            $rows[] = $this->row($guest, $preference, $status, $sendDate, $sensitive);
        }

        return [
            'send_date' => $sendDate,
            'send_state' => $sendDate <= $today ? 'sent' : 'scheduled',
            'kpis' => [
                'guests' => $passengers->count(),
                'bookings' => $passengers->map(fn ($passenger): int => $passenger->guest->booking_id)->unique()->count(),
                'answered' => $answered,
                'total' => $passengers->count(),
                'celebrations' => $celebrations,
                'accessibility_or_medical' => $restricted,
            ],
            'guests' => $rows,
        ];
    }

    private function status(?GuestPreference $preference, string $sendDate, string $today): PreferenceStatus
    {
        if ($preference instanceof GuestPreference) {
            return PreferenceStatus::Answered;
        }

        return $sendDate <= $today ? PreferenceStatus::SentNoReply : PreferenceStatus::Scheduled;
    }

    /**
     * @return array{
     *     guest_id: int,
     *     name: string,
     *     booking_reference: string,
     *     email: string|null,
     *     email_note: string|null,
     *     cabin: string,
     *     status: PreferenceStatus,
     *     status_label: string,
     *     answered_at: string|null,
     *     source: PreferenceSource|null,
     *     send_date: string,
     *     dietary: string|null,
     *     celebration: string|null,
     *     activity: string|null,
     *     accessibility_provided: bool,
     *     emergency_contact_provided: bool,
     *     accessibility?: string|null,
     *     emergency_contact?: string|null
     * }
     */
    private function row(
        Guest $guest,
        ?GuestPreference $preference,
        PreferenceStatus $status,
        string $sendDate,
        bool $sensitive,
    ): array {
        $email = trim((string) $guest->email);
        $row = [
            'guest_id' => $guest->id,
            'name' => $guest->displayName(),
            'booking_reference' => (string) ($guest->booking->reference ?? ''),
            'email' => $email === '' ? null : $email,
            'email_note' => $email === '' ? 'no email — sent to lead guest' : null,
            'cabin' => GuestCabin::label($guest),
            'status' => $status,
            'status_label' => $status->label(),
            'answered_at' => $preference?->answered_at === null ? null : Iso::utc($preference->answered_at),
            'source' => $preference?->source,
            'send_date' => $sendDate,
            'dietary' => $preference?->answer('diet'),
            'celebration' => $preference?->answer('celebr'),
            'activity' => $this->activity($preference),
            'accessibility_provided' => trim((string) $preference?->accessibility) !== '',
            'emergency_contact_provided' => trim((string) $preference?->emergency_contact) !== '',
        ];

        if ($sensitive) {
            $row['accessibility'] = $this->blankToNull($preference?->accessibility);
            $row['emergency_contact'] = $this->blankToNull($preference?->emergency_contact);
        }

        return $row;
    }

    private function activity(?GuestPreference $preference): ?string
    {
        $parts = array_values(array_filter([
            $preference?->answer('intensity'),
            $preference?->answer('time'),
        ], fn (?string $value): bool => $value !== null && $value !== ''));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function blankToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
