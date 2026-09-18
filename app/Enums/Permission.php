<?php

declare(strict_types=1);

namespace App\Enums;

// TODO(Sprint 1) Complete the Permission list (panel.rms / panel.crm, remaining
// resource verbs). This stub is only the cases that can be derived with certainty
// from doc 01 §19. No roles table and no gates yet.

enum Permission: string
{
    case BookingsViewAll = 'bookings.view_all';
    case BookingsCreate = 'bookings.create';
    case BookingsChangeStatus = 'bookings.change_status';
    case BookingsMove = 'bookings.move';
    case BookingsDelete = 'bookings.delete';
    case UsersManage = 'users.manage';
    case PaymentsMarkWireReceived = 'payments.mark_wire_received';
    case RefundsExecute = 'refunds.execute';
    case CommissionsOverrideCap = 'commissions.override_cap';
    case BookingsOverdueDecision = 'bookings.overdue_decision';
    case RefundsApprove = 'refunds.approve';
    case RatesManage = 'rates.manage';
    case RulesManage = 'rules.manage';

    public function label(): string
    {
        return match ($this) {
            self::BookingsViewAll => 'View all bookings',
            self::BookingsCreate => 'Create bookings',
            self::BookingsChangeStatus => 'Change booking status',
            self::BookingsMove => 'Move bookings',
            self::BookingsDelete => 'Delete bookings',
            self::UsersManage => 'Manage users',
            self::PaymentsMarkWireReceived => 'Mark wires received',
            self::RefundsExecute => 'Execute refunds',
            self::CommissionsOverrideCap => 'Override commission cap',
            self::BookingsOverdueDecision => 'OPS-007 overdue decisions',
            self::RefundsApprove => 'Approve refunds',
            self::RatesManage => 'Manage rates',
            self::RulesManage => 'Manage business rules',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::BookingsViewAll,
            self::BookingsCreate,
            self::BookingsChangeStatus,
            self::BookingsMove,
            self::BookingsDelete => 'bookings',
            self::UsersManage => 'users',
            self::PaymentsMarkWireReceived,
            self::RefundsExecute => 'finance',
            self::CommissionsOverrideCap,
            self::BookingsOverdueDecision,
            self::RefundsApprove,
            self::RatesManage,
            self::RulesManage => 'director',
        };
    }
}
