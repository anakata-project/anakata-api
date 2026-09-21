<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentKind;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LogicException;

/**
 * @property int $id
 * @property int $booking_id
 * @property DocumentKind $kind
 * @property string|null $number
 * @property int $version
 * @property string|null $reason
 * @property int|null $payment_id
 * @property int $payment_key
 * @property array<string, mixed> $snapshot
 * @property string $file_path
 * @property string $file_sha256
 * @property Carbon $issued_at
 * @property int|null $issued_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read Payment|null $payment
 * @property-read User|null $issuedBy
 * @property-read Collection<int, Delivery> $deliveries
 */
#[Fillable([
    'booking_id',
    'kind',
    'number',
    'version',
    'reason',
    'payment_id',
    'snapshot',
    'file_path',
    'file_sha256',
    'issued_at',
    'issued_by',
])]
class Document extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'version' => 'integer',
            'payment_id' => 'integer',
            'payment_key' => 'integer',
            'snapshot' => 'array',
            'issued_at' => 'datetime',
            'issued_by' => 'integer',
        ];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Documents are append-only.');
        }

        return parent::save($options);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Documents are append-only.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Documents are append-only.');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
