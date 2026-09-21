<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CardStripe = 'CARD_STRIPE';
    case StripeLink = 'STRIPE_LINK';
    case Wire = 'WIRE';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::CardStripe => 'Card (Stripe)',
            self::StripeLink => 'Stripe payment link',
            self::Wire => 'Wire transfer',
            self::Other => 'Other',
        };
    }

    /**
     * True for every case today. Exists so a future gateway-only method can
     * opt out of the record-payment form without the method looking unused.
     */
    public function recordable(): bool
    {
        return true;
    }
}
