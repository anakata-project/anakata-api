<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\PermissionCollection;
use App\Enums\Permission;
use App\Models\Concerns\HasAuditColumns;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use LogicException;

/**
 * @property Collection<int, Permission> $permissions
 * @property-read int|null $users_count
 */
#[Fillable(['name', 'slug', 'description', 'permissions', 'is_system'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasAuditColumns, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'permissions' => PermissionCollection::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            if ($role->isAdmin()) {
                $role->permissions = collect();
            }
        });

        static::updating(function (Role $role): void {
            if ($role->isDirty('slug')) {
                throw new LogicException('A role slug cannot be changed after creation.');
            }
        });
    }

    public function isAdmin(): bool
    {
        return $this->slug === 'admin';
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }

    /**
     * @return list<string>
     */
    public function permissionValues(): array
    {
        if ($this->isAdmin()) {
            return array_map(
                fn (Permission $permission): string => $permission->value,
                Permission::cases(),
            );
        }

        return $this->permissions
            ->map(fn (Permission $permission): string => $permission->value)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function flagValues(): array
    {
        $permissions = $this->isAdmin()
            ? collect(Permission::cases())
            : $this->permissions;

        return $permissions
            ->filter(fn (Permission $permission): bool => $permission->isFlag())
            ->map(fn (Permission $permission): string => $permission->value)
            ->values()
            ->all();
    }
}
