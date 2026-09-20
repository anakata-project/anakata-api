<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Support\Bookings\Transitions;

test('the sprint 4 table matches TRANS minus sprint 5 states', function (): void {
    expect(array_map(fn (BookingStatus $status): string => $status->value, Transitions::targets(BookingStatus::Requested)))
        ->toBe(['PENDING_PAYMENT', 'CONFIRMED', 'RELEASED', 'CANCELLED']);
    expect(array_map(fn (BookingStatus $status): string => $status->value, Transitions::targets(BookingStatus::PendingPayment)))
        ->toBe(['CONFIRMED', 'CANCELLED']);
    expect(array_map(fn (BookingStatus $status): string => $status->value, Transitions::targets(BookingStatus::Confirmed)))
        ->toBe(['FULLY_PAID', 'CANCELLED']);
    expect(array_map(fn (BookingStatus $status): string => $status->value, Transitions::targets(BookingStatus::FullyPaid)))
        ->toBe(['ON_BOARD', 'CANCELLED_POSTPAID']);
    expect(array_map(fn (BookingStatus $status): string => $status->value, Transitions::targets(BookingStatus::OnBoard)))
        ->toBe(['COMPLETED']);
    expect(array_map(fn (BookingStatus $status): string => $status->value, Transitions::targets(BookingStatus::OnHoldAgency)))
        ->toBe(['CONFIRMED', 'RELEASED', 'CANCELLED']);

    foreach ([
        BookingStatus::Completed,
        BookingStatus::Cancelled,
        BookingStatus::CancelledPostpaid,
        BookingStatus::Released,
        BookingStatus::Overdue,
        BookingStatus::Waitlisted,
    ] as $terminal) {
        expect(Transitions::targets($terminal))->toBe([]);
    }
});

test('reason is required for cancellations, manual fully paid and release', function (): void {
    expect(Transitions::reasonRequired(BookingStatus::Cancelled))->toBeTrue();
    expect(Transitions::reasonRequired(BookingStatus::CancelledPostpaid))->toBeTrue();
    expect(Transitions::reasonRequired(BookingStatus::FullyPaid))->toBeTrue();
    expect(Transitions::reasonRequired(BookingStatus::Released))->toBeTrue();
    expect(Transitions::reasonRequired(BookingStatus::Confirmed))->toBeFalse();
    expect(Transitions::reasonRequired(BookingStatus::OnBoard))->toBeFalse();
});

test('date guards for on board and completed', function (): void {
    expect(Transitions::dateGuardAllows(BookingStatus::OnBoard, '2027-11-06', '2027-11-07', '2027-11-14'))->toBeFalse();
    expect(Transitions::dateGuardAllows(BookingStatus::OnBoard, '2027-11-07', '2027-11-07', '2027-11-14'))->toBeTrue();
    expect(Transitions::dateGuardAllows(BookingStatus::Completed, '2027-11-13', '2027-11-07', '2027-11-14'))->toBeFalse();
    expect(Transitions::dateGuardAllows(BookingStatus::Completed, '2027-11-14', '2027-11-07', '2027-11-14'))->toBeTrue();
    expect(Transitions::dateGuardAllows(BookingStatus::Cancelled, '2027-01-01', '2027-11-07', '2027-11-14'))->toBeTrue();
});

test('the enum delegates to the table', function (): void {
    expect(BookingStatus::Confirmed->allowedTransitions())->toBe(Transitions::targets(BookingStatus::Confirmed));
});
