<?php

declare(strict_types=1);

namespace App\Support\Complete;

use App\Enums\PaymentKind;
use App\Enums\PaymentLinkStatus;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Support\Payments\Ledger;

final readonly class CompleteDue
{
    public function __construct(
        public PaymentKind $kind,
        public int $amount,
        public ?string $payUrl,
    ) {}

    public static function for(Booking $booking): self
    {
        $booking->loadMissing('paymentLinks');

        $kind = Ledger::paidFresh($booking) < $booking->depositAmount()
            ? PaymentKind::Deposit
            : PaymentKind::Balance;

        $open = $booking->paymentLinks->first(
            fn (PaymentLink $link): bool => $link->kind === $kind
                && $link->status === PaymentLinkStatus::Open,
        );

        if ($open instanceof PaymentLink) {
            return new self($kind, $open->amount, $open->url);
        }

        $amount = $kind === PaymentKind::Deposit
            ? $booking->depositAmount()
            : $booking->balanceFresh();

        return new self($kind, $amount, null);
    }
}
