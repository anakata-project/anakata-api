<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\ConsentPurpose;
use App\Models\Contact;
use App\Models\ContactConsent;

final class ContactConsentSummary
{
    /**
     * Latest MARKETING row in the contact consent register.
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

        $latest = ContactConsent::query()
            ->where('contact_id', $contact->id)
            ->where('purpose', ConsentPurpose::Marketing)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        return [
            'marketing' => $latest instanceof ContactConsent && $latest->granted,
            'transactional' => true,
        ];
    }
}
