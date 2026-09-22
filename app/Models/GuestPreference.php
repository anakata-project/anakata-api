<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SensitiveEncrypted;
use App\Enums\PreferenceSource;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\GuestPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $guest_id
 * @property int $version
 * @property array<string, string> $answers
 * @property string|null $accessibility
 * @property string|null $emergency_contact
 * @property PreferenceSource $source
 * @property int|null $recorded_by
 * @property Carbon $answered_at
 * @property Carbon|null $purged_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Guest $guest
 * @property-read User|null $recordedBy
 */
#[Fillable([
    'guest_id',
    'version',
    'answers',
    'accessibility',
    'emergency_contact',
    'source',
    'recorded_by',
    'answered_at',
    'purged_at',
])]
class GuestPreference extends Model
{
    /** @use HasFactory<GuestPreferenceFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'answers' => 'array',
            'accessibility' => SensitiveEncrypted::class,
            'emergency_contact' => SensitiveEncrypted::class,
            'source' => PreferenceSource::class,
            'answered_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('purged_at');
    }

    public function answer(string $key): ?string
    {
        $value = $this->answers[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
