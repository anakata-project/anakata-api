<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ContactMerge;
use App\Models\User;

final class ContactMergePolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function undo(User $actor, ContactMerge $merge): bool
    {
        return $actor->hasPermission(Permission::ContactsMerge);
    }
}
