<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Action;
use App\Exceptions\ConflictException;
use App\Models\Role;
use App\Models\User;
use App\Support\History\History;
use App\Support\Users\LastAdminGuard;

final class UpdateUser extends Action
{
    public function handle(User $actor, User $user, ?string $name, ?Role $role): User
    {
        $user->loadMissing('role');

        $nameChanged = $name !== null && $name !== $user->name;
        $newRole = $role !== null && $role->id !== $user->role_id ? $role : null;

        if (! $nameChanged && $newRole === null) {
            return $user;
        }

        return $this->transaction(function () use ($actor, $user, $name, $nameChanged, $newRole): User {
            $previousName = $user->name;
            $previousRoleName = $user->role?->name;

            if ($newRole !== null) {
                LastAdminGuard::assertCanLoseAdmin($user);

                if ($actor->id === $user->id) {
                    throw new ConflictException('You cannot change your own role.');
                }

                $user->role_id = $newRole->id;
            }

            if ($nameChanged) {
                $user->name = $name;
            }

            $user->save();
            $user->load('role');

            if ($nameChanged) {
                History::record(
                    $user,
                    'user.updated',
                    ['name' => $previousName],
                    ['name' => $user->name],
                );
            }

            if ($newRole !== null) {
                History::record(
                    $user,
                    'user.role_changed',
                    ['role' => $previousRoleName],
                    ['role' => $newRole->name],
                );
            }

            return $user;
        });
    }
}
