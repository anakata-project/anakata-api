<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

use App\Models\Contact;
use App\Models\Guest;

/**
 * The guest who is the contact: the same lowercase-trim as Contact::normalizeEmail,
 * plus-addressing kept. A companion's address is not the contact's.
 */
final class ContactGuest
{
    public static function matches(?string $guestEmail, ?string $contactEmail): bool
    {
        $guest = Contact::normalizeEmail($guestEmail);
        $contact = Contact::normalizeEmail($contactEmail);

        return $guest !== null && $contact !== null && $guest === $contact;
    }

    public static function guestIs(Guest $guest, Contact $contact): bool
    {
        return self::matches($guest->email, $contact->email);
    }
}
