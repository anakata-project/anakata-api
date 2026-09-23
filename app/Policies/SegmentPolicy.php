<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Segment;
use App\Models\User;

final class SegmentPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function view(User $actor, Segment $segment): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::ContactsManage);
    }

    public function update(User $actor, Segment $segment): bool
    {
        return $actor->hasPermission(Permission::ContactsManage);
    }
}
