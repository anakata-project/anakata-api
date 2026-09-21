<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\ConsentDocument;
use App\Models\Consent;
use App\Models\Contact;

final class ContactConsentSummary
{
    /**
     * Latest MARKETING consent across the contact's bookings. Sprint 10 replaces this with the register.
     *
     * @return array{marketing: bool, transactional: true}
     */
    public static function for(Contact $contact): array
    {
        if (array_key_exists('marketing_consent', $contact->getAttributes())) {
            return [
                'marketing' => (bool) $contact->getAttribute('marketing_consent'),
                'transactional' => true,
            ];
        }

        $latest = Consent::query()
            ->select('consents.*')
            ->join('bookings', 'bookings.id', '=', 'consents.booking_id')
            ->where('bookings.contact_id', $contact->id)
            ->whereNull('bookings.deleted_at')
            ->where('consents.document', ConsentDocument::Marketing)
            ->orderByDesc('consents.id')
            ->first();

        return [
            'marketing' => $latest instanceof Consent && ! $latest->withdrawn,
            'transactional' => true,
        ];
    }
}
