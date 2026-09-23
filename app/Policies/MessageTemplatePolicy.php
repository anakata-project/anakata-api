<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MessageTemplate;
use App\Models\User;

final class MessageTemplatePolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function view(User $actor, MessageTemplate $template): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function draft(User $actor, MessageTemplate $template): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function preview(User $actor, MessageTemplate $template): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function testSend(User $actor, MessageTemplate $template): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function publish(User $actor, MessageTemplate $template): bool
    {
        return $actor->hasPermission(Permission::RulesManage);
    }
}
