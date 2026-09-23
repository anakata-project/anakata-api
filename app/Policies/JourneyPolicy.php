<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Journey;
use App\Models\User;

final class JourneyPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function view(User $actor, Journey $journey): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function update(User $actor, Journey $journey): bool
    {
        return $actor->hasPermission(Permission::RulesManage);
    }
}
