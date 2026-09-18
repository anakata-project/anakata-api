<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(Permission $permission): bool
    {
        $this->loadMissing('role');

        if ($this->role === null) {
            return false;
        }

        if ($this->role->isAdmin()) {
            return true;
        }

        return $this->role->permissions->contains($permission);
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permissions(): Collection
    {
        $this->loadMissing('role');

        $role = $this->role;

        if ($role === null) {
            return collect();
        }

        if ($role->isAdmin()) {
            /** @var Collection<int, Permission> $permissions */
            $permissions = collect(Permission::cases());

            return $permissions;
        }

        return $role->permissions;
    }

    /**
     * @return list<'rms'|'crm'>
     */
    public function sections(): array
    {
        $sections = [];

        if ($this->hasPermission(Permission::PanelRms)) {
            $sections[] = 'rms';
        }

        if ($this->hasPermission(Permission::PanelCrm)) {
            $sections[] = 'crm';
        }

        return $sections;
    }
}
