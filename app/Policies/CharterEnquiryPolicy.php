<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CharterEnquiry;
use App\Models\User;

final class CharterEnquiryPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function update(User $actor, CharterEnquiry $enquiry): bool
    {
        return $actor->hasPermission(Permission::BookingsCreate);
    }
}
