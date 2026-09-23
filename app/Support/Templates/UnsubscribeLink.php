<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Models\Contact;

final class UnsubscribeLink
{
    public static function for(Contact $contact): string
    {
        $token = hash_hmac('sha256', (string) $contact->id, (string) config('app.key'));

        return rtrim((string) config('anakata.engine_url'), '/').'/unsubscribe/'.$token;
    }
}
