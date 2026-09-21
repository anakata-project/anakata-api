<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Contact;
use App\Models\User;

final class ContactPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function view(User $actor, Contact $contact): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function update(User $actor, Contact $contact): bool
    {
        return $actor->hasPermission(Permission::ContactsManage);
    }
}
