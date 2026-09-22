<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\TaskKind;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $context
 * @property int|null $contact_id
 * @property int|null $deal_id
 * @property int|null $booking_id
 * @property int|null $charter_enquiry_id
 * @property int|null $refund_request_id
 * @property int|null $payment_id
 * @property int|null $subject_request_id
 * @property int|null $owner_id
 * @property Permission|null $needs_permission
 * @property Carbon $due_at
 * @property TaskSource $source
 * @property TaskKind $kind
 * @property string|null $idempotency_key
 * @property TaskStatus $status
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $outcome
 */
#[Fillable([
    'title',
    'context',
    'contact_id',
    'deal_id',
    'booking_id',
    'charter_enquiry_id',
    'refund_request_id',
    'payment_id',
    'subject_request_id',
    'owner_id',
    'needs_permission',
    'due_at',
    'source',
    'kind',
    'idempotency_key',
    'status',
    'closed_at',
    'closed_by',
    'outcome',
])]
class CrmTask extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'needs_permission' => Permission::class,
            'due_at' => 'datetime',
            'source' => TaskSource::class,
            'kind' => TaskKind::class,
            'status' => TaskStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function historyLabel(): string
    {
        return $this->title;
    }
}
