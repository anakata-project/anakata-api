<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryKind: string
{
    case Invoice = 'INVOICE';
    case FinalInvoice = 'FINAL_INVOICE';
    case Summary = 'SUMMARY';
    case Receipt = 'RECEIPT';
    case Reminder = 'REMINDER';
    case Voucher = 'VOUCHER';
    case Pretrip = 'PRETRIP';
    case PaymentLink = 'PAYMENT_LINK';
    case WireInstructions = 'WIRE_INSTRUCTIONS';
    case DataChaser = 'DATA_CHASER';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Booking Confirmation & Invoice',
            self::FinalInvoice => 'Final Invoice',
            self::Summary => 'Booking Summary',
            self::Receipt => 'Payment Confirmation',
            self::Reminder => 'Balance reminder',
            self::Voucher => 'Transfer Voucher',
            self::Pretrip => 'Pre-trip Itinerary',
            self::PaymentLink => 'Payment link',
            self::WireInstructions => 'Wire Instructions',
            self::DataChaser => 'Passenger details needed',
        };
    }

    public function attachesPdf(): bool
    {
        return $this !== self::Reminder && $this !== self::PaymentLink && $this !== self::DataChaser;
    }

    public function copiesAgency(): bool
    {
        return $this === self::Invoice || $this === self::FinalInvoice;
    }

    public function isDocumentKind(): bool
    {
        return $this->documentKind() !== null;
    }

    public function documentKind(): ?DocumentKind
    {
        return match ($this) {
            self::Invoice => DocumentKind::Invoice,
            self::FinalInvoice => DocumentKind::FinalInvoice,
            self::Summary => DocumentKind::Summary,
            self::Receipt => DocumentKind::Receipt,
            self::Voucher => DocumentKind::Voucher,
            self::Pretrip => DocumentKind::Pretrip,
            self::WireInstructions => DocumentKind::WireInstructions,
            self::Reminder, self::PaymentLink, self::DataChaser => null,
        };
    }

    public static function fromDocument(DocumentKind $kind): self
    {
        return match ($kind) {
            DocumentKind::Invoice => self::Invoice,
            DocumentKind::FinalInvoice => self::FinalInvoice,
            DocumentKind::Summary => self::Summary,
            DocumentKind::Receipt => self::Receipt,
            DocumentKind::Voucher => self::Voucher,
            DocumentKind::Pretrip => self::Pretrip,
            DocumentKind::WireInstructions => self::WireInstructions,
        };
    }
}
