<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\WaitlistEntry;

final class WaitlistEntryPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::BookingsCreate);
    }

    public function notify(User $actor, WaitlistEntry $entry): bool
    {
        return $actor->hasPermission(Permission::BookingsCreate);
    }

    public function remove(User $actor, WaitlistEntry $entry): bool
    {
        return $actor->hasPermission(Permission::BookingsCreate);
    }
}
