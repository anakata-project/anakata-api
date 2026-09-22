<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Campaign;
use App\Models\User;

final class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PanelCrm);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(Permission::PanelCrm);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CampaignsManage);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission(Permission::CampaignsManage);
    }
}
