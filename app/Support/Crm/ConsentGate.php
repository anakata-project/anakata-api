<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\ConsentPurpose;
use App\Models\Contact;
use App\Models\ContactConsent;

final class ConsentGate
{
    public static function allows(Contact $contact, ConsentPurpose $purpose): bool
    {
        $latest = ContactConsent::query()
            ->where('contact_id', $contact->id)
            ->where('purpose', $purpose)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        return $latest instanceof ContactConsent && $latest->granted;
    }
}
