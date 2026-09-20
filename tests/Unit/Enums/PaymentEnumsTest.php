<?php

declare(strict_types=1);

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

test('payment kind letters match H3', function (): void {
    expect(PaymentKind::Deposit->letter())->toBe('D');
    expect(PaymentKind::Balance->letter())->toBe('B');
    expect(PaymentKind::Extras->letter())->toBe('E');
    expect(PaymentKind::Refund->letter())->toBe('R');
    expect(PaymentKind::Other->letter())->toBe('O');
});

test('payment labels match the prototype', function (): void {
    expect(PaymentMethod::CardStripe->label())->toBe('Card (Stripe)');
    expect(PaymentMethod::StripeLink->label())->toBe('Stripe payment link');
    expect(PaymentMethod::Wire->label())->toBe('Wire transfer');
    expect(PaymentStatus::Settled->label())->toBe('Settled');
    expect(PaymentStatus::AwaitingWire->label())->toBe('Awaiting wire');
    expect(PaymentStatus::Refunded->label())->toBe('Refunded');
    expect(PaymentKind::Deposit->label())->toBe('Deposit');
});

test('countsAsPaid is everything except awaiting wire', function (): void {
    expect(PaymentStatus::Settled->countsAsPaid())->toBeTrue();
    expect(PaymentStatus::Refunded->countsAsPaid())->toBeTrue();
    expect(PaymentStatus::AwaitingWire->countsAsPaid())->toBeFalse();
    expect(PaymentStatus::paidValues())->toBe([
        PaymentStatus::Settled->value,
        PaymentStatus::Refunded->value,
    ]);
});
