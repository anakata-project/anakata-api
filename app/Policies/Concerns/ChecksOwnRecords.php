<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ChecksOwnRecords
{
    public function ownsOrMayActOnAny(User $user, Model $record, string $ownerColumn = 'owner_id'): bool
    {
        if ($user->hasPermission(Permission::RecordsActOnAny)) {
            return true;
        }

        $value = $record->{$ownerColumn};

        if ($value === null) {
            return false;
        }

        return (int) $value === (int) $user->id;
    }
}
