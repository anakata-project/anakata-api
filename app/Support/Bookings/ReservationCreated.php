<?php

declare(strict_types=1);

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;

final readonly class ReservationCreated
{
    /**
     * @param  Collection<int, Booking>  $bookings
     * @param  list<string>  $warnings
     */
    public function __construct(
        public Collection $bookings,
        public ?Group $group,
        public array $warnings,
    ) {}
}
