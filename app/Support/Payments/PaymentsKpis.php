<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\Config\CurrentConfig;
use App\Support\BusinessTime;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

final class PaymentsKpis
{
    /**
     * Statuses that still owe. Matches the prototype's `pend` (balance > 0,
     * not REQUESTED) and includes overdue CONFIRMED / ON_HOLD_AGENCY rows.
     *
     * @return list<string>
     */
    public static function owingStatuses(): array
    {
        return [
            BookingStatus::PendingPayment->value,
            BookingStatus::Confirmed->value,
            BookingStatus::OnHoldAgency->value,
        ];
    }

    /**
     * @return array{
     *     collected: int,
     *     deposits: int,
     *     pending: int,
     *     pending_count: int,
     *     overdue_count: int,
     *     overdue_amount: int,
     *     commission_accrued: int,
     *     cabin_deposit_pct: int,
     *     charter_deposit_pct: int,
     *     cabin_balance_days: int,
     *     commission_payable_days: int,
     *     commission_cap_pct: int,
     *     wire_window_hours: int
     * }
     */
    public static function for(User $actor, ?string $from, ?string $to): array
    {
        $config = app(CurrentConfig::class);
        $terms = $config->rates()->terms;
        $row = self::aggregate($actor, $from, $to);

        return [
            'collected' => (int) ($row->collected ?? 0),
            'deposits' => (int) ($row->deposits ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'pending_count' => (int) ($row->pending_count ?? 0),
            'overdue_count' => (int) ($row->overdue_count ?? 0),
            'overdue_amount' => (int) ($row->overdue_amount ?? 0),
            'commission_accrued' => (int) ($row->commission_accrued ?? 0),
            'cabin_deposit_pct' => $terms->cabinDepositPct,
            'charter_deposit_pct' => $terms->charterDepositPct,
            'cabin_balance_days' => $terms->cabinBalanceDays,
            'commission_payable_days' => $config->businessRules()->commission->payableDaysAfterCruise,
            'commission_cap_pct' => $config->businessRules()->commission->capPct,
            'wire_window_hours' => $config->businessRules()->payments->wireWindowHours,
        ];
    }

    private static function aggregate(User $actor, ?string $from, ?string $to): stdClass
    {
        $viewAll = $actor->hasPermission(Permission::BookingsViewAll);
        [$balanceSql, $paid] = Booking::balanceSql();
        $owing = self::owingStatuses();
        $overdueStatuses = [
            BookingStatus::Confirmed->value,
            BookingStatus::OnHoldAgency->value,
        ];
        $cancelled = [
            BookingStatus::Cancelled->value,
            BookingStatus::CancelledPostpaid->value,
        ];
        $today = BusinessTime::now()->toDateString();
        $dueSql = 'COALESCE(bookings.balance_due_date_override, DATE_SUB((
            SELECT departures.date FROM departures WHERE departures.id = bookings.departure_id
        ), INTERVAL bookings.balance_days DAY))';

        $owingIn = implode(', ', array_fill(0, count($owing), '?'));
        $overdueIn = implode(', ', array_fill(0, count($overdueStatuses), '?'));
        $cancelledIn = implode(', ', array_fill(0, count($cancelled), '?'));

        $pendingWhen = 'bookings.status IN ('.$owingIn.') AND ('.$balanceSql.') > 0';
        $overdueWhen = 'bookings.status IN ('.$overdueIn.') AND ('.$balanceSql.') > 0 AND ? > '.$dueSql;

        $collected = self::paidSumQuery($actor, $viewAll, $from, $to, depositsOnly: false);
        $deposits = self::paidSumQuery($actor, $viewAll, $from, $to, depositsOnly: true);

        $row = self::visibleBookings($actor, $viewAll, $from, $to)
            ->toBase()
            ->selectRaw(
                '('.$collected->toSql().') as collected, '.
                '('.$deposits->toSql().') as deposits, '.
                'COALESCE(SUM(CASE WHEN '.$pendingWhen.' THEN ('.$balanceSql.') ELSE 0 END), 0) as pending, '.
                'COALESCE(SUM(CASE WHEN '.$pendingWhen.' THEN 1 ELSE 0 END), 0) as pending_count, '.
                'COALESCE(SUM(CASE WHEN '.$overdueWhen.' THEN 1 ELSE 0 END), 0) as overdue_count, '.
                'COALESCE(SUM(CASE WHEN '.$overdueWhen.' THEN ('.$balanceSql.') ELSE 0 END), 0) as overdue_amount, '.
                'COALESCE(SUM(CASE WHEN bookings.commission_approved = 1 AND bookings.commission_pct IS NOT NULL '.
                'AND bookings.status NOT IN ('.$cancelledIn.') '.
                'THEN ROUND(bookings.total * bookings.commission_pct / 100) ELSE 0 END), 0) as commission_accrued',
                [
                    ...$collected->getBindings(),
                    ...$deposits->getBindings(),
                    ...$owing,
                    ...$paid,
                    ...$paid,
                    ...$owing,
                    ...$paid,
                    ...$overdueStatuses,
                    ...$paid,
                    $today,
                    ...$overdueStatuses,
                    ...$paid,
                    $today,
                    ...$paid,
                    ...$cancelled,
                ],
            )
            ->first();

        return $row instanceof stdClass ? $row : (object) [];
    }

    /**
     * @return Builder<Booking>
     */
    private static function visibleBookings(User $actor, bool $viewAll, ?string $from, ?string $to): Builder
    {
        return Booking::query()
            ->when(
                ! $viewAll,
                fn (Builder $query) => $query->where('bookings.owner_id', $actor->id),
            )
            ->when(
                $from !== null,
                fn (Builder $query) => $query->whereHas(
                    'departure',
                    fn (Builder $departure) => $departure->whereDate('date', '>=', $from),
                ),
            )
            ->when(
                $to !== null,
                fn (Builder $query) => $query->whereHas(
                    'departure',
                    fn (Builder $departure) => $departure->whereDate('date', '<=', $to),
                ),
            );
    }

    /**
     * @return Builder<Payment>
     */
    private static function paidSumQuery(
        User $actor,
        bool $viewAll,
        ?string $from,
        ?string $to,
        bool $depositsOnly,
    ): Builder {
        return Payment::query()
            ->whereHas(
                'booking',
                function (Builder $booking) use ($actor, $viewAll): void {
                    if (! $viewAll) {
                        $booking->where('owner_id', $actor->id);
                    }
                },
            )
            ->whereIn('payments.status', PaymentStatus::paidValues())
            ->when(
                $depositsOnly,
                fn (Builder $query) => $query->where('payments.kind', PaymentKind::Deposit),
                fn (Builder $query) => $query->whereIn('payments.kind', [
                    PaymentKind::Deposit,
                    PaymentKind::Balance,
                ]),
            )
            ->when(
                $from !== null,
                fn (Builder $query) => $query->whereDate('payments.paid_at', '>=', $from),
            )
            ->when(
                $to !== null,
                fn (Builder $query) => $query->whereDate('payments.paid_at', '<=', $to),
            )
            ->selectRaw('COALESCE(SUM(payments.amount), 0)');
    }
}
