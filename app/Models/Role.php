<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\PermissionCollection;
use App\Enums\Permission;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use LogicException;

/**
 * @property Collection<int, Permission> $permissions
 */
#[Fillable(['name', 'slug', 'description', 'permissions', 'is_system'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

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
}
