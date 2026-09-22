<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DeliveryKind;
use App\Models\Booking;
use App\Models\Guest;

final class Recipients
{
    public function resolve(Booking $booking, DeliveryKind $kind): RecipientSet
    {
        $booking->loadMissing(['contact', 'group.coordinator', 'agency', 'guests']);

        $cc = [];

        if ($kind === DeliveryKind::Summary || $kind === DeliveryKind::DataChaser || $kind === DeliveryKind::Questionnaire) {
            $to = $this->usable($this->leadGuestEmail($booking));
            $role = 'lead guest';

            if ($to === null) {
                $to = $this->usable($this->clientOfRecord($booking));
                $role = $this->clientRole($booking);
            }
        } else {
            $to = $this->usable($this->clientOfRecord($booking));
            $role = $this->clientRole($booking);
        }

        if ($to === null) {
            return new RecipientSet([], [], 'No email address for the '.$role);
        }

        if ($kind->copiesAgency()) {
            $agencyEmail = $this->usable($booking->agency?->email);

            if ($agencyEmail !== null && strcasecmp($agencyEmail, $to) !== 0) {
                $cc[] = $agencyEmail;
            }
        }

        return new RecipientSet([$to], $cc, null);
    }

    public function usableAddress(?string $email): ?string
    {
        return $this->usable($email);
    }

    private function clientOfRecord(Booking $booking): ?string
    {
        if ($booking->group !== null) {
            return $booking->group->coordinator->email;
        }

        if (is_string($booking->billing_email) && trim($booking->billing_email) !== '') {
            return $booking->billing_email;
        }

        return $booking->contact->email;
    }

    private function clientRole(Booking $booking): string
    {
        return $booking->group !== null
            ? 'group coordinator'
            : 'client of record';
    }

    private function leadGuestEmail(Booking $booking): ?string
    {
        $lead = $booking->guests->first(
            fn (Guest $guest): bool => $guest->is_lead && $this->usable($guest->email) !== null,
        );

        return $lead?->email;
    }

    private function usable(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = trim($email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }
}
