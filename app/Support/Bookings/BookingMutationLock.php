<?php

declare(strict_types=1);

namespace App\Support\Bookings;

use App\Exceptions\ConflictException;
use App\Models\Booking;
use App\Support\Inventory\DepartureLocks;

final class BookingMutationLock
{
    public const CHANGED = 'This booking changed — reload and try again.';

    /**
     * Lock departure row(s) (ascending), then the booking. Verify the locked
     * booking still sits on the plain-read departure.
     *
     * @param  list<int>  $departureIds
     */
    public static function acquire(Booking $booking, int $expectedDepartureId, array $departureIds = []): Booking
    {
        $ids = $departureIds === [] ? [$expectedDepartureId] : $departureIds;

        DepartureLocks::lockMany($ids);

        $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

        if ((int) $locked->departure_id !== $expectedDepartureId) {
            throw new ConflictException(self::CHANGED);
        }

        return $locked;
    }
}
