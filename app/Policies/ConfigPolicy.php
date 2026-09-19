<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

abstract class ConfigPolicy extends Policy
{
    abstract protected function viewPermission(): Permission;

    abstract protected function publishPermission(): Permission;

    public function view(User $actor): bool
    {
        return $actor->hasPermission($this->viewPermission());
    }

    public function publish(User $actor): bool|Response
    {
        return $actor->hasPermission($this->publishPermission());
    }
}
