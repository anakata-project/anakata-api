<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Payments\SettleGatewayPayment;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Exceptions\UnresolvableStripeEvent;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\StripeEvent;
use App\Support\Stripe\StripeMoney;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

final class ProcessStripeEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $stripeEventId) {}

    public function handle(SettleGatewayPayment $settle): void
    {
        $event = StripeEvent::query()
            ->where('stripe_event_id', $this->stripeEventId)
            ->firstOrFail();

        if ($event->processed_at !== null) {
            return;
        }

        try {
            match ($event->type) {
                'checkout.session.completed' => $this->settleCheckout($event, $settle),
                'charge.refunded' => $this->matchRefund($event),
                default => $this->ignore($event),
            };
        } catch (UnresolvableStripeEvent $exception) {
            $event->error = $exception->getMessage();
            $event->save();

            throw $exception;
        }
    }

    private function settleCheckout(StripeEvent $event, SettleGatewayPayment $settle): void
    {
        $session = $this->object($event);
        $paymentIntent = $this->string($session['payment_intent'] ?? null);
        $amountCents = $session['amount_total'] ?? $session['amount_subtotal'] ?? null;

        if ($paymentIntent === null || ! is_int($amountCents) && ! is_numeric($amountCents)) {
            throw new UnresolvableStripeEvent('checkout.session.completed is missing payment_intent or amount.');
        }

        $link = $this->findLink($session);
        $booking = $this->findBooking($session, $link);

        if (! $booking instanceof Booking) {
            throw new UnresolvableStripeEvent('Could not resolve a booking for checkout.session.completed.');
        }

        $kind = $this->kindFrom($session, $link);

        $settle->handle($booking, [
            'kind' => $kind,
            'method' => PaymentMethod::StripeLink,
            'amount' => StripeMoney::fromCents((int) $amountCents),
            'gateway_id' => $paymentIntent,
            'payment_link' => $link,
        ], null, system: true);

        $this->markProcessed($event, 'settled');
    }

    private function matchRefund(StripeEvent $event): void
    {
        $charge = $this->object($event);
        $refundId = $this->refundId($charge);

        if ($refundId === null) {
            $this->markProcessed($event, 'unmatched');

            return;
        }

        $payment = Payment::query()
            ->where('kind', PaymentKind::Refund)
            ->where('gateway_id', $refundId)
            ->first();

        if ($payment instanceof Payment) {
            if ($payment->gateway_id !== $refundId) {
                $payment->gateway_id = $refundId;
                $payment->save();
            }

            $this->markProcessed($event, 'matched_refund');

            return;
        }

        $this->markProcessed($event, 'unmatched');
    }

    private function ignore(StripeEvent $event): void
    {
        $this->markProcessed($event, 'ignored');
    }

    private function markProcessed(StripeEvent $event, string $resolution): void
    {
        $event->processed_at = Carbon::now();
        $event->resolution = $resolution;
        $event->error = null;
        $event->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function object(StripeEvent $event): array
    {
        $data = $event->payload['data'] ?? [];
        $object = is_array($data) ? ($data['object'] ?? []) : [];

        return is_array($object) ? $object : [];
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function findLink(array $session): ?PaymentLink
    {
        $paymentLinkId = $this->string($session['payment_link'] ?? null);

        if ($paymentLinkId === null) {
            return null;
        }

        return PaymentLink::query()->where('stripe_id', $paymentLinkId)->first();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function findBooking(array $session, ?PaymentLink $link): ?Booking
    {
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $bookingId = $this->string($metadata['booking_id'] ?? null);

        if ($bookingId !== null && ctype_digit($bookingId)) {
            $booking = Booking::query()->find((int) $bookingId);

            if ($booking instanceof Booking) {
                return $booking;
            }
        }

        $reference = $this->string($metadata['booking_reference'] ?? null);

        if ($reference !== null) {
            $booking = Booking::query()
                ->where(function (Builder $query) use ($reference): void {
                    $query->where('reference', $reference)
                        ->orWhere('request_reference', $reference);
                })
                ->first();

            if ($booking instanceof Booking) {
                return $booking;
            }
        }

        return $link?->booking;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function kindFrom(array $session, ?PaymentLink $link): PaymentKind
    {
        if ($link instanceof PaymentLink) {
            return $link->kind;
        }

        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $kind = $this->string($metadata['kind'] ?? null);

        if ($kind !== null) {
            return PaymentKind::from($kind);
        }

        throw new UnresolvableStripeEvent('checkout.session.completed has no payment kind.');
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    private function refundId(array $charge): ?string
    {
        $refunds = $charge['refunds'] ?? null;

        if (is_array($refunds) && isset($refunds['data']) && is_array($refunds['data'])) {
            $latest = $refunds['data'][0] ?? null;

            if (is_array($latest)) {
                return $this->string($latest['id'] ?? null);
            }
        }

        return $this->string($charge['refund'] ?? null);
    }

    private function string(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_array($value) && isset($value['id']) && is_string($value['id']) && $value['id'] !== '') {
            return $value['id'];
        }

        return null;
    }
}
