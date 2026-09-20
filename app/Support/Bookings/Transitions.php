<?php

declare(strict_types=1);

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Support\BusinessTime;

final class Transitions
{
    /**
     * @return list<BookingStatus>
     */
    public static function targets(BookingStatus $from): array
    {
        return match ($from) {
            BookingStatus::Requested => [
                BookingStatus::PendingPayment,
                BookingStatus::Confirmed,
                BookingStatus::Released,
                BookingStatus::Cancelled,
            ],
            BookingStatus::PendingPayment => [
                BookingStatus::Confirmed,
                BookingStatus::Cancelled,
            ],
            BookingStatus::Confirmed => [
                BookingStatus::FullyPaid,
                BookingStatus::Cancelled,
            ],
            BookingStatus::FullyPaid => [
                BookingStatus::OnBoard,
                BookingStatus::CancelledPostpaid,
            ],
            BookingStatus::OnBoard => [
                BookingStatus::Completed,
            ],
            default => [],
        };
    }

    public static function reasonRequired(BookingStatus $to): bool
    {
        return in_array($to, [
            BookingStatus::Cancelled,
            BookingStatus::CancelledPostpaid,
            BookingStatus::FullyPaid,
            BookingStatus::Released,
        ], true);
    }

    public static function dateGuardAllows(
        BookingStatus $to,
        string $today,
        string $departureDate,
        string $returnDate,
    ): bool {
        return match ($to) {
            BookingStatus::OnBoard => $today >= $departureDate,
            BookingStatus::Completed => $today >= $returnDate,
            default => true,
        };
    }

    public static function dateGuardAllowsFor(Booking $booking, BookingStatus $to): bool
    {
        $booking->loadMissing(['departure.itinerary']);

        return self::dateGuardAllows(
            $to,
            BusinessTime::now()->toDateString(),
            $booking->departure->date->toDateString(),
            $booking->departure->returnDate()->toDateString(),
        );
    }

    /**
     * @return list<BookingStatus>
     */
    public static function legalTargets(Booking $booking): array
    {
        return array_values(array_filter(
            self::targets($booking->status),
            fn (BookingStatus $to): bool => self::dateGuardAllowsFor($booking, $to),
        ));
    }

    /**
     * @return list<array{to: string, reason_required: bool}>
     */
    public static function allowedFor(Booking $booking, User $actor): array
    {
        if (! $actor->hasPermission(Permission::BookingsChangeStatus)) {
            return [];
        }

        if (! app(BookingPolicy::class)->ownsOrMayActOnAny($actor, $booking)) {
            return [];
        }

        return array_map(
            fn (BookingStatus $to): array => [
                'to' => $to->value,
                'reason_required' => self::reasonRequired($to),
            ],
            self::legalTargets($booking),
        );
    }

    /**
     * @param  list<BookingStatus>  $allowed
     */
    public static function illegalMessage(BookingStatus $from, BookingStatus $to, array $allowed): string
    {
        $list = $allowed === []
            ? 'none'
            : implode(', ', array_map(fn (BookingStatus $status): string => $status->value, $allowed));

        return 'Cannot change status from '.$from->value.' to '.$to->value.'. Allowed: '.$list.'.';
    }

    public static function statusLabel(BookingStatus $status): string
    {
        return str_replace('_', ' ', $status->value);
    }
}
