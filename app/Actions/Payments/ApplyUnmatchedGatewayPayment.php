<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Action;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\Stripe\StripeGateway;

final class ApplyUnmatchedGatewayPayment extends Action
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly SettleGatewayPayment $settle,
    ) {}

    /**
     * @param  array{stripe_id: string, kind: PaymentKind|string}  $data
     */
    public function handle(Booking $booking, array $data, User $actor): Payment
    {
        $charge = $this->stripe->retrieveCharge((string) $data['stripe_id']);
        $kind = $data['kind'] instanceof PaymentKind
            ? $data['kind']
            : PaymentKind::from((string) $data['kind']);

        $gatewayId = $charge->paymentIntentId ?? $charge->id;

        return $this->settle->handle($booking, [
            'kind' => $kind,
            'method' => PaymentMethod::CardStripe,
            'amount' => $charge->amountUsd,
            'gateway_id' => $gatewayId,
            'note' => 'applied from gateway reconciliation',
        ], $actor);
    }
}
