<?php

declare(strict_types=1);

namespace App\Support\Complete;

use App\Enums\ConsentDocument;
use App\Models\Booking;
use App\Models\Consent;
use App\Support\Config\Documents\ConsentVersions;

final class CompletePayability
{
    public static function canPay(Booking $booking, ConsentVersions $versions, ?string $payUrl): bool
    {
        if ($payUrl === null || $payUrl === '') {
            return false;
        }

        $booking->loadMissing('consents');

        foreach (ConsentDocument::cases() as $document) {
            if (! $document->required()) {
                continue;
            }

            $current = $versions->for($document);
            $accepted = $booking->consents->contains(
                fn (Consent $consent): bool => $consent->document === $document
                    && ! $consent->withdrawn
                    && $consent->version === $current,
            );

            if (! $accepted) {
                return false;
            }
        }

        return true;
    }
}
