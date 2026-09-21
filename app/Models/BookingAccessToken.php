<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingAccessTokenPurpose;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\BookingAccessTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $token_hash
 * @property BookingAccessTokenPurpose $purpose
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property string $page_url
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 */
#[Fillable([
    'booking_id',
    'token_hash',
    'purpose',
    'expires_at',
    'revoked_at',
    'page_url',
])]
class BookingAccessToken extends Model
{
    /** @use HasFactory<BookingAccessTokenFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => BookingAccessTokenPurpose::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return self::query()->where('token_hash', self::hashToken($token))->first();
    }
}
