<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Actions\Alerts\RaiseAlert;
use App\Actions\Alerts\ResolveAlert;
use App\Enums\AlertKind;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Alert;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\StripeEvent;
use App\Support\Alerts\AlertKeys;
use App\Support\Crm\TaskSweep;
use App\Support\Money;
use App\Support\Payments\Ledger;
use App\Support\Stripe\StripeMoney;
use Illuminate\Support\Collection;

final class LedgerCheck
{
    public function __construct(
        private readonly RaiseAlert $raise,
        private readonly ResolveAlert $resolve,
    ) {}

    public function run(): void
    {
        $keys = [
            ...$this->stripePayments(),
            ...$this->paidFigures(),
            ...$this->refunds(),
        ];

        $this->resolveCleared($keys);
    }

    /**
     * @return list<string>
     */
    private function stripePayments(): array
    {
        $payments = Payment::query()
            ->with('booking')
            ->where('status', PaymentStatus::Settled)
            ->whereIn('method', [PaymentMethod::CardStripe, PaymentMethod::StripeLink])
            ->where('amount', '>', 0)
            ->whereNotNull('gateway_id')
            ->orderBy('id')
            ->get();

        /** @var Collection<string, Collection<int, Payment>> $groups */
        $groups = $payments->groupBy(fn (Payment $payment): string => self::paymentIntent((string) $payment->gateway_id));
        $events = $this->latestCheckoutEvents();
        $keys = [];

        foreach ($groups as $intent => $group) {
            $sum = (int) $group->sum('amount');
            $event = $events[$intent] ?? null;
            $eventUsd = is_array($event) ? $event['usd'] : null;
            $currency = is_array($event) ? $event['currency'] : null;
            $cents = is_array($event) ? $event['cents'] : null;
            $matches = $cents !== null
                && $cents >= 0
                && $currency === 'usd'
                && StripeMoney::toCents($sum) === $cents;

            if ($matches) {
                continue;
            }

            $booking = $group->sortBy('booking_id')->first();
            $names = $group
                ->sortBy('booking_id')
                ->map(fn (Payment $payment): string => TaskSweep::reference($payment->booking))
                ->unique()
                ->implode(', ');
            $eventLabel = $cents === null || $cents < 0 ? 'none' : Money::format((int) $eventUsd);
            $key = AlertKeys::ledgerStripe($intent);

            $this->raise->handle(
                AlertKind::LedgerDrift,
                $key,
                'Ledger drift '.$names,
                $names.': settled card payments '.Money::format($sum).' against Stripe '.$eventLabel.' (stripe payment '.$intent.').',
                bookingId: $booking instanceof Payment ? $booking->booking_id : null,
            );
            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function paidFigures(): array
    {
        [$sql, $bindings] = Booking::paidSql();
        $keys = [];

        Booking::query()
            ->select('bookings.*')
            ->selectRaw('('.$sql.') as ledger_paid_sql', $bindings)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.booking_id', 'bookings.id');
            })
            ->orderBy('bookings.id')
            ->each(function (Booking $booking) use (&$keys): void {
                $sqlPaid = (int) $booking->getAttribute('ledger_paid_sql');
                $fresh = Ledger::paidFresh($booking);

                if ($sqlPaid === $fresh) {
                    return;
                }

                $reference = TaskSweep::reference($booking);
                $key = AlertKeys::ledgerPaid($booking->id);

                $this->raise->handle(
                    AlertKind::LedgerDrift,
                    $key,
                    'Ledger drift '.$reference,
                    $reference.': paid figure '.Money::format($sqlPaid).' against settled payments '.Money::format($fresh).' (paid).',
                    bookingId: $booking->id,
                );
                $keys[] = $key;
            });

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function refunds(): array
    {
        $payments = Payment::query()
            ->with('booking')
            ->where('kind', PaymentKind::Refund)
            ->whereIn('method', [PaymentMethod::CardStripe, PaymentMethod::StripeLink])
            ->whereIn('id', function ($query): void {
                $query->select('executed_payment_id')
                    ->from('refund_requests')
                    ->whereNotNull('executed_payment_id');
            })
            ->orderBy('id')
            ->get();

        $charges = $this->refundCharges();
        $matched = [];
        $keys = [];

        foreach ($charges as $chargeId => $charge) {
            $rows = $payments->filter(
                fn (Payment $payment): bool => in_array((string) $payment->gateway_id, $charge['refund_ids'], true),
            );

            foreach ($rows as $payment) {
                $matched[$payment->id] = true;
            }

            if ($rows->isEmpty()) {
                continue;
            }

            $sum = (int) $rows->sum(fn (Payment $payment): int => abs($payment->amount));
            $matches = $charge['currency'] === 'usd' && StripeMoney::toCents($sum) === $charge['cents'];

            if ($matches) {
                continue;
            }

            $names = $rows
                ->sortBy('booking_id')
                ->map(fn (Payment $payment): string => TaskSweep::reference($payment->booking))
                ->unique()
                ->implode(', ');
            $first = $rows->sortBy('booking_id')->first();
            $key = AlertKeys::ledgerRefund($chargeId);

            $this->raise->handle(
                AlertKind::LedgerDrift,
                $key,
                'Ledger drift '.$names,
                $names.': executed refunds '.Money::format($sum).' against Stripe '.Money::format($charge['usd']).' (stripe refund '.$chargeId.').',
                bookingId: $first instanceof Payment ? $first->booking_id : null,
            );
            $keys[] = $key;
        }

        foreach ($payments as $payment) {
            if (isset($matched[$payment->id])) {
                continue;
            }

            $reference = TaskSweep::reference($payment->booking);
            $key = AlertKeys::ledgerRefund('payment:'.$payment->id);

            $this->raise->handle(
                AlertKind::LedgerDrift,
                $key,
                'Ledger drift '.$reference,
                $reference.': executed refund '.Money::format(abs($payment->amount)).' against Stripe none (stripe refund).',
                bookingId: $payment->booking_id,
                paymentId: $payment->id,
            );
            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * @param  list<string>  $keys
     */
    private function resolveCleared(array $keys): void
    {
        $query = Alert::query()
            ->where('kind', AlertKind::LedgerDrift)
            ->unresolved()
            ->orderBy('id');

        if ($keys !== []) {
            $query->whereNotIn('base_key', $keys);
        }

        $query->each(function (Alert $alert): void {
            $this->resolve->handle($alert, 'the next ledger run found no difference');
        });
    }

    public static function paymentIntent(string $gatewayId): string
    {
        $hash = strpos($gatewayId, '#');

        return $hash === false ? $gatewayId : substr($gatewayId, 0, $hash);
    }

    /**
     * @return array<string, array{cents: int, usd: int, currency: string}>
     */
    private function latestCheckoutEvents(): array
    {
        $events = [];

        foreach ($this->eventsOfType('checkout.session.completed') as $event) {
            $object = self::object($event);
            $intent = self::string($object['payment_intent'] ?? null);

            if ($intent === null || isset($events[$intent])) {
                continue;
            }

            $cents = $object['amount_total'] ?? $object['amount_subtotal'] ?? null;

            if (! is_int($cents) && ! is_numeric($cents)) {
                $events[$intent] = ['cents' => -1, 'usd' => 0, 'currency' => ''];

                continue;
            }

            $cents = (int) $cents;
            $events[$intent] = [
                'cents' => $cents,
                'usd' => StripeMoney::fromCents($cents),
                'currency' => strtolower(self::string($object['currency'] ?? null) ?? ''),
            ];
        }

        return $events;
    }

    /**
     * @return array<string, array{refund_ids: list<string>, cents: int, usd: int, currency: string}>
     */
    private function refundCharges(): array
    {
        /** @var array<string, array{refund_ids: list<string>, cents: int, usd: int, currency: string, latest: bool}> $charges */
        $charges = [];

        foreach ($this->eventsOfType('charge.refunded') as $event) {
            $object = self::object($event);
            $chargeId = self::string($object['id'] ?? null);

            if ($chargeId === null) {
                continue;
            }

            $ids = self::refundIds($object);

            if (! isset($charges[$chargeId])) {
                $cents = $object['amount_refunded'] ?? null;
                $cents = is_int($cents) || is_numeric($cents) ? (int) $cents : -1;
                $charges[$chargeId] = [
                    'refund_ids' => $ids,
                    'cents' => $cents,
                    'usd' => $cents < 0 ? 0 : StripeMoney::fromCents($cents),
                    'currency' => strtolower(self::string($object['currency'] ?? null) ?? ''),
                    'latest' => true,
                ];

                continue;
            }

            $charges[$chargeId]['refund_ids'] = array_values(array_unique([
                ...$charges[$chargeId]['refund_ids'],
                ...$ids,
            ]));
        }

        $result = [];

        foreach ($charges as $chargeId => $charge) {
            unset($charge['latest']);
            $result[$chargeId] = $charge;
        }

        return $result;
    }

    /**
     * Latest event first, so the first row per charge is the one whose amount is kept.
     *
     * @return list<StripeEvent>
     */
    private function eventsOfType(string $type): array
    {
        return StripeEvent::query()
            ->where('type', $type)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function object(StripeEvent $event): array
    {
        $data = $event->payload['data'] ?? [];
        $object = is_array($data) ? ($data['object'] ?? []) : [];

        return is_array($object) ? $object : [];
    }

    /**
     * @param  array<string, mixed>  $charge
     * @return list<string>
     */
    private static function refundIds(array $charge): array
    {
        $ids = [];
        $refunds = $charge['refunds'] ?? null;

        if (is_array($refunds) && isset($refunds['data']) && is_array($refunds['data'])) {
            foreach ($refunds['data'] as $refund) {
                if (! is_array($refund)) {
                    continue;
                }

                $id = self::string($refund['id'] ?? null);

                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        $single = self::string($charge['refund'] ?? null);

        if ($single !== null) {
            $ids[] = $single;
        }

        return array_values(array_unique($ids));
    }

    private static function string(mixed $value): ?string
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
