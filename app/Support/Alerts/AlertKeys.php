<?php

declare(strict_types=1);

namespace App\Support\Alerts;

use App\Enums\DeliveryKind;

final class AlertKeys
{
    public static function overdue(int $bookingId, string $dueDate): string
    {
        return 'overdue:'.$bookingId.':'.$dueDate;
    }

    public static function cap(int $bookingId): string
    {
        return 'cap:'.$bookingId;
    }

    public static function wire(int $paymentId): string
    {
        return 'wire:'.$paymentId;
    }

    public static function sla(string $taskKey): string
    {
        return 'sla:'.$taskKey;
    }

    public static function delivery(?int $documentId, int $bookingId, DeliveryKind $kind): string
    {
        if ($documentId !== null) {
            return 'delivery:'.$documentId;
        }

        return 'delivery:'.$bookingId.':'.$kind->value;
    }
}
