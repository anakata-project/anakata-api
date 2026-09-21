<?php

declare(strict_types=1);

namespace App\Actions\Guests;

use App\Enums\Permission;
use App\Models\Guest;
use App\Models\User;
use App\Support\Guests\Age;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class ApplyGuestFields
{
    /** @var list<string> */
    private const SENSITIVE_NOTES = [
        'medical_note',
        'dietary_note',
        'accessibility_note',
    ];

    /** @var list<string> */
    private const PLAIN = [
        'first_name',
        'last_name',
        'dob',
        'nationality',
        'ecuador_resident',
        'passport_expiry',
        'email',
        'insurance_declared',
        'guardian_name',
        'guardian_relationship',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function apply(Guest $guest, array $data, User $actor): void
    {
        $canViewSensitive = $actor->hasPermission(Permission::GuestsViewSensitive);

        foreach (self::PLAIN as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if (in_array($field, ['first_name', 'last_name'], true)) {
                $guest->{$field} = is_string($value) ? $value : '';

                continue;
            }

            $guest->{$field} = $value === '' ? null : $value;
        }

        if (array_key_exists('passport_no', $data)) {
            $passport = $data['passport_no'];
            $passport = is_string($passport) ? $passport : null;

            if ($passport !== null && $passport !== '') {
                $guest->passport_no = $passport;
            } elseif ($canViewSensitive) {
                $guest->passport_no = null;
            }
        }

        foreach (self::SENSITIVE_NOTES as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $note = $data[$field];
            $guest->{$field} = is_string($note) && $note !== '' ? $note : null;
        }

        $this->applyGuardian($guest, $data, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyGuardian(Guest $guest, array $data, User $actor): void
    {
        if (! Age::isMinorNow($guest->dob)) {
            $guest->guardian_name = null;
            $guest->guardian_relationship = null;
            $guest->guardian_consented_at = null;
            $guest->guardian_recorded_by = null;

            return;
        }

        if (array_key_exists('guardian_name', $data)) {
            $name = $data['guardian_name'];
            $guest->guardian_name = is_string($name) && $name !== '' ? $name : null;
        }

        if (array_key_exists('guardian_relationship', $data)) {
            $relationship = $data['guardian_relationship'];
            $guest->guardian_relationship = is_string($relationship) && $relationship !== ''
                ? $relationship
                : null;
        }

        if (! array_key_exists('guardian_consented', $data)) {
            return;
        }

        if ($data['guardian_consented'] === true) {
            if ($guest->guardian_name === null || $guest->guardian_name === '') {
                throw ValidationException::withMessages([
                    'guardian_name' => ['A guardian name is required when consent is set.'],
                ]);
            }

            if ($guest->guardian_consented_at === null) {
                $guest->guardian_consented_at = Carbon::now();
                $guest->guardian_recorded_by = $actor->id;
            }

            return;
        }

        $guest->guardian_consented_at = null;
        $guest->guardian_recorded_by = null;
    }
}
