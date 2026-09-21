<?php

declare(strict_types=1);

namespace App\Support\Guests;

use App\Models\Guest;

final class GuestFieldLabels
{
    /** @var array<string, string> */
    public const MAP = [
        'first_name' => 'first name',
        'last_name' => 'surname',
        'dob' => 'date of birth',
        'nationality' => 'nationality',
        'ecuador_resident' => 'EC residency',
        'passport_no' => 'passport number',
        'passport_expiry' => 'passport expiry',
        'email' => 'email',
        'insurance_declared' => 'insurance declaration',
        'medical_note' => 'medical note',
        'dietary_note' => 'dietary note',
        'accessibility_note' => 'accessibility note',
        'guardian_name' => 'guardian name',
        'guardian_relationship' => 'guardian relationship',
    ];

    /**
     * @return list<string>
     */
    public static function changed(Guest $guest): array
    {
        $labels = [];

        foreach (self::MAP as $field => $label) {
            if ($guest->wasChanged($field)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }
}
