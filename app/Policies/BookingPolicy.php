<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\User;

final class BookingPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function view(User $actor, Booking $booking): bool
    {
        return $actor->hasPermission(Permission::BookingsViewAll)
            || (int) $booking->owner_id === (int) $actor->id;
    }

    public function viewHistory(User $actor, Booking $booking): bool
    {
        return $this->view($actor, $booking);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::BookingsCreate);
    }
}
