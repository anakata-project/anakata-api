<?php

declare(strict_types=1);

namespace App\Enums;

enum DealStage: string
{
    case NewLead = 'NEW_LEAD';
    case Qualifying = 'QUALIFYING';
    case Quoted = 'QUOTED';
    case Negotiation = 'NEGOTIATION';
    case DepositPending = 'DEPOSIT_PENDING';
    case BookingConfirmed = 'BOOKING_CONFIRMED';
    case WonCompleted = 'WON_COMPLETED';
    case Lost = 'LOST';

    public function label(): string
    {
        return match ($this) {
            self::NewLead => 'New lead',
            self::Qualifying => 'Qualifying',
            self::Quoted => 'Quoted',
            self::Negotiation => 'Negotiation',
            self::DepositPending => 'Deposit pending',
            self::BookingConfirmed => 'Booking confirmed',
            self::WonCompleted => 'Won — completed',
            self::Lost => 'Lost',
        };
    }

    public function owner(): string
    {
        return match ($this) {
            self::NewLead, self::Qualifying, self::Quoted, self::Negotiation => 'CRM',
            self::DepositPending, self::BookingConfirmed, self::WonCompleted => 'RMS',
            self::Lost => 'RMS or CRM',
        };
    }

    public function isStored(): bool
    {
        return in_array($this, self::stored(), true);
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /**
     * @return list<self>
     */
    public static function stored(): array
    {
        return [
            self::NewLead,
            self::Qualifying,
            self::Quoted,
            self::Negotiation,
            self::Lost,
        ];
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [
            self::NewLead,
            self::Qualifying,
            self::Quoted,
            self::Negotiation,
        ];
    }

    /**
     * @return list<self>
     */
    public static function columns(): array
    {
        return [
            self::NewLead,
            self::Qualifying,
            self::Quoted,
            self::Negotiation,
            self::DepositPending,
            self::BookingConfirmed,
            self::WonCompleted,
            self::Lost,
        ];
    }

    public function weightsForecast(): bool
    {
        return in_array($this, [
            self::NewLead,
            self::Qualifying,
            self::Quoted,
            self::Negotiation,
            self::DepositPending,
        ], true);
    }
}
