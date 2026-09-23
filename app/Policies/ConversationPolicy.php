<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Conversation;
use App\Models\User;

final class ConversationPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function view(User $actor, Conversation $conversation): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function update(User $actor, Conversation $conversation): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function reply(User $actor, Conversation $conversation): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function link(User $actor, Conversation $conversation): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }
}
