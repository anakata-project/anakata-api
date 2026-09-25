<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Action;
use App\Actions\Payments\SettleGatewayPayment;
use App\Enums\BookingStatus;
use App\Enums\CheckoutPath;
use App\Enums\CheckoutSessionStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\CheckoutSession;
use App\Services\Stripe\RetrievedCheckoutSession;
use App\Services\Stripe\StripeGateway;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stripe is the authority. A completed Checkout Session settles the deposit
 * even when the webhook has not arrived yet.
 */
final class SettlePaidEngineCheckout extends Action
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly SettleGatewayPayment $settle,
    ) {}

    public function outstanding(): int
    {
        $settled = 0;

        $sessions = CheckoutSession::query()
            ->where('status', CheckoutSessionStatus::Submitted)
            ->where('path', CheckoutPath::PayDeposit)
            ->whereNotNull('stripe_checkout_session_id')
            ->get();

        foreach ($sessions as $session) {
            if ($this->handle($session)) {
                $settled++;
            }
        }

        return $settled;
    }

    public function handle(CheckoutSession $session): bool
    {
        if ($session->path !== CheckoutPath::PayDeposit || $session->stripe_checkout_session_id === null) {
            return false;
        }

        try {
            $retrieved = $this->stripe->retrieveCheckoutSession($session->stripe_checkout_session_id);
        } catch (Throwable $exception) {
            Log::warning('Could not retrieve Stripe checkout session.', [
                'checkout_session_id' => $session->id,
                'stripe_id' => $session->stripe_checkout_session_id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        return $this->apply($session, $retrieved);
    }

    public function apply(CheckoutSession $session, RetrievedCheckoutSession $retrieved): bool
    {
        if ($retrieved->status !== 'complete' || $retrieved->paymentIntentId === null) {
            return false;
        }

        $session->load('bookings');
        $settled = false;

        foreach ($session->bookings as $booking) {
            if (! in_array($booking->status, [BookingStatus::Requested, BookingStatus::PendingPayment], true)) {
                continue;
            }

            $this->settle->handle($booking, [
                'kind' => PaymentKind::Deposit,
                'method' => PaymentMethod::StripeLink,
                'amount' => $booking->depositAmount(),
                'gateway_id' => $retrieved->paymentIntentId.'#'.$booking->id,
            ], null, system: true);

            $settled = true;
        }

        return $settled;
    }
}
