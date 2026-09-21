<?php

declare(strict_types=1);

use App\Enums\PaymentKind;
use App\Enums\PaymentLinkStatus;
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
    expect(PaymentLinkStatus::Open->label())->toBe('Open');
    expect(PaymentLinkStatus::Paid->label())->toBe('Paid');
});

test('recordable excludes refund and keeps every method', function (): void {
    expect(PaymentKind::Deposit->recordable())->toBeTrue();
    expect(PaymentKind::Balance->recordable())->toBeTrue();
    expect(PaymentKind::Extras->recordable())->toBeTrue();
    expect(PaymentKind::Other->recordable())->toBeTrue();
    expect(PaymentKind::Refund->recordable())->toBeFalse();

    foreach (PaymentMethod::cases() as $method) {
        expect($method->recordable())->toBeTrue();
    }
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
