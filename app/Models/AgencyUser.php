<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgencyUserStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use App\Notifications\PortalResetPasswordNotification;
use Database\Factories\AgencyUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $agency_id
 * @property string $name
 * @property string $email
 * @property AgencyUserStatus $status
 * @property string|null $password
 * @property Carbon|null $accepted_at
 * @property Carbon|null $last_login_at
 * @property string|null $invite_token_hash
 * @property Carbon|null $invite_sent_at
 * @property Carbon|null $invite_expires_at
 * @property int|null $invited_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Agency $agency
 * @property-read User|null $invitedByUser
 */
#[Fillable(['agency_id', 'name', 'email', 'status'])]
#[Hidden(['password', 'remember_token', 'invite_token_hash'])]
class AgencyUser extends Authenticatable
{
    /** @use HasFactory<AgencyUserFactory> */
    use HasAuditColumns, HasFactory, Notifiable, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgencyUserStatus::class,
            'password' => 'hashed',
            'accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'invite_sent_at' => 'datetime',
            'invite_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new PortalResetPasswordNotification((string) $token));
    }
}
