<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Models\Booking;
use App\Models\Deal;
use App\Models\Group;

final class OpenDealReference
{
    public static function of(Booking $booking, ?Deal $deal = null): string
    {
        if (is_string($booking->reference) && $booking->reference !== '') {
            return $booking->reference;
        }

        if (is_string($booking->request_reference) && $booking->request_reference !== '') {
            return $booking->request_reference;
        }

        $groupId = $deal instanceof Deal && $deal->group_id !== null
            ? $deal->group_id
            : $booking->group_id;
        $group = $groupId !== null ? Group::query()->find($groupId) : null;

        return $group instanceof Group ? $group->reference : 'the booking';
    }
}
