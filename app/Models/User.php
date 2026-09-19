<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property UserStatus $status
 * @property Carbon|null $invited_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $disabled_at
 * @property Carbon|null $last_login_at
 */
#[Fillable([
    'name',
    'email',
    'password',
    'role_id',
    'status',
    'invited_at',
    'activated_at',
    'disabled_at',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasAuditColumns, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
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

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower($value),
        );
    }
}
