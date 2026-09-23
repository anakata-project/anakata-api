<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AutomationSetting;
use App\Models\User;

final class AutomationSettingPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function update(User $actor, AutomationSetting $setting): bool
    {
        return $actor->hasPermission(Permission::RulesManage);
    }
}
