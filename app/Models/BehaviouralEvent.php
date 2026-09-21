<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BehaviouralEventName;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\BehaviouralEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_id
 * @property string $session_id
 * @property int|null $contact_id
 * @property BehaviouralEventName $name
 * @property array<string, mixed> $params
 * @property Carbon $occurred_at
 * @property Carbon $received_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Contact|null $contact
 */
#[Fillable([
    'event_id',
    'session_id',
    'contact_id',
    'name',
    'params',
    'occurred_at',
    'received_at',
])]
class BehaviouralEvent extends Model
{
    /** @use HasFactory<BehaviouralEventFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => BehaviouralEventName::class,
            'params' => 'array',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
