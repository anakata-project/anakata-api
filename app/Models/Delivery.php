<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTriggeredBy;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $document_id
 * @property DeliveryKind $kind
 * @property string $idempotency_key
 * @property list<string> $to
 * @property list<string> $cc
 * @property string $subject
 * @property DeliveryStatus $status
 * @property string|null $error
 * @property string|null $blocked_reason
 * @property Carbon|null $sent_at
 * @property DeliveryTriggeredBy $triggered_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read Document|null $document
 */
#[Fillable([
    'booking_id',
    'document_id',
    'kind',
    'idempotency_key',
    'to',
    'cc',
    'subject',
    'status',
    'error',
    'blocked_reason',
    'sent_at',
    'triggered_by',
])]
class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /** @var list<string> */
    private const LIFECYCLE = [
        'status',
        'error',
        'blocked_reason',
        'sent_at',
        'updated_at',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DeliveryKind::class,
            'to' => 'array',
            'cc' => 'array',
            'status' => DeliveryStatus::class,
            'sent_at' => 'datetime',
            'triggered_by' => DeliveryTriggeredBy::class,
        ];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            foreach (array_keys($this->getDirty()) as $column) {
                if (! in_array($column, self::LIFECYCLE, true)) {
                    throw new LogicException('Delivery identifying columns are immutable.');
                }
            }
        }

        return parent::save($options);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        foreach (array_keys($attributes) as $column) {
            if (! in_array($column, self::LIFECYCLE, true)) {
                throw new LogicException('Delivery identifying columns are immutable.');
            }
        }

        return parent::update($attributes, $options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Deliveries are append-only.');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
