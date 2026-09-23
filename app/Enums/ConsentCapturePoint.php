<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsentCapturePoint: string
{
    case EngineForm = 'ENGINE_FORM';
    case EngineBanner = 'ENGINE_BANNER';
    case Staff = 'STAFF';
    case BookingLogBackfill = 'BOOKING_LOG_BACKFILL';
    case SubjectRequest = 'SUBJECT_REQUEST';
    case Unsubscribe = 'UNSUBSCRIBE';

    public function label(): string
    {
        return match ($this) {
            self::EngineForm => 'Engine form',
            self::EngineBanner => 'Engine banner',
            self::Staff => 'Staff',
            self::BookingLogBackfill => 'Booking log backfill',
            self::SubjectRequest => 'Subject request',
            self::Unsubscribe => 'Unsubscribe',
        };
    }
}
