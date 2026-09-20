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
}
