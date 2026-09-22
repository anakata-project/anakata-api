<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DeliveryKind;
use App\Enums\PaymentKind;
use App\Models\Booking;
use App\Models\PaymentLink;

final class DeliverySubject
{
    public static function forDocument(DeliveryKind $kind, Booking $booking): string
    {
        $ref = $booking->displayReference() ?? 'booking';

        return match ($kind) {
            DeliveryKind::Invoice => 'Booking confirmation & invoice — '.$ref,
            DeliveryKind::FinalInvoice => 'Final invoice — '.$ref,
            DeliveryKind::Summary => 'Your Anakata booking summary — '.$ref,
            DeliveryKind::Receipt => 'Payment confirmation — '.$ref,
            DeliveryKind::Voucher => 'Transfer voucher — '.$ref,
            DeliveryKind::Pretrip => 'Your expedition itinerary — '.$ref,
            DeliveryKind::WireInstructions => 'Wire transfer instructions — '.$ref,
            DeliveryKind::Reminder, DeliveryKind::PaymentLink => $kind->label().' — '.$ref,
            DeliveryKind::DataChaser => 'Passenger details needed — '.$ref,
            DeliveryKind::Questionnaire => 'Your preferences questionnaire — '.$ref,
        };
    }

    public static function forReminder(Booking $booking): string
    {
        return 'Your Anakata balance — due '.$booking->balanceDueDate()->toDateString();
    }

    public static function forPaymentLink(Booking $booking, PaymentLink $link): string
    {
        $ref = $booking->displayReference() ?? 'booking';

        return match ($link->kind) {
            PaymentKind::Deposit => 'Complete your Anakata reservation — '.$ref,
            PaymentKind::Balance => 'Pay your Anakata balance — '.$ref,
            default => 'Anakata payment link — '.$ref,
        };
    }
}
