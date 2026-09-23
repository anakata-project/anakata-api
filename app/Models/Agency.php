<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgencyStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use App\Support\Rounding;
use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $reference
 * @property string $name
 * @property string $contact
 * @property string $email
 * @property string|null $country
 * @property string|null $network
 * @property int $commission_pct
 * @property string $payment_terms
 * @property AgencyStatus $status
 * @property Carbon $requested_at
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property string|null $decision_reason
 * @property Carbon|null $portal_suspended_at
 * @property int|null $portal_suspended_by
 * @property string|null $portal_suspend_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $decidedBy
 * @property-read User|null $portalSuspendedBy
 * @property-read Collection<int, AgencyUser> $users
 * @property-read Collection<int, Booking> $bookings
 */
#[Fillable([
    'reference',
    'name',
    'contact',
    'email',
    'country',
    'network',
    'commission_pct',
    'payment_terms',
    'status',
    'requested_at',
    'decided_at',
    'decided_by',
    'decision_reason',
    'portal_suspended_at',
    'portal_suspended_by',
    'portal_suspend_reason',
])]
class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_pct' => 'integer',
            'status' => AgencyStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'portal_suspended_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AgencyUser, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(AgencyUser::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function portalSuspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'portal_suspended_by');
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }

    public function historyLabel(): string
    {
        return $this->reference;
    }

    public function netOf(int $public): int
    {
        return Rounding::halfUp($public * (100 - $this->commission_pct) / 100);
    }

    public function isPortalSuspended(): bool
    {
        return $this->portal_suspended_at !== null;
    }
}
