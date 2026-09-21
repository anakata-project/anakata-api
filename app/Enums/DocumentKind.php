<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentKind: string
{
    case Invoice = 'INVOICE';
    case FinalInvoice = 'FINAL_INVOICE';
    case Summary = 'SUMMARY';
    case Receipt = 'RECEIPT';
    case Voucher = 'VOUCHER';
    case Pretrip = 'PRETRIP';
    case WireInstructions = 'WIRE_INSTRUCTIONS';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Booking Confirmation & Invoice',
            self::FinalInvoice => 'Final Invoice',
            self::Summary => 'Booking Summary',
            self::Receipt => 'Payment Confirmation',
            self::Voucher => 'Transfer Voucher',
            self::Pretrip => 'Pre-trip Itinerary',
            self::WireInstructions => 'Wire Instructions',
        };
    }

    public function isNumbered(): bool
    {
        return $this === self::Invoice || $this === self::FinalInvoice;
    }

    public function isReceipt(): bool
    {
        return $this === self::Receipt;
    }
}
