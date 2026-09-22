<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubjectRequestChannel;
use App\Enums\SubjectRequestStatus;
use App\Enums\SubjectRequestType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contact_id
 * @property SubjectRequestType $type
 * @property Carbon $received_at
 * @property Carbon $due_at
 * @property SubjectRequestChannel $channel
 * @property string|null $verified_how
 * @property SubjectRequestStatus $status
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 * @property string|null $outcome
 * @property string|null $export_path
 * @property int|null $created_by
 * @property-read Contact $contact
 */
#[Fillable([
    'contact_id',
    'type',
    'received_at',
    'due_at',
    'channel',
    'verified_how',
    'status',
    'completed_at',
    'completed_by',
    'outcome',
    'export_path',
])]
class SubjectRequest extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SubjectRequestType::class,
            'received_at' => 'datetime',
            'due_at' => 'datetime',
            'channel' => SubjectRequestChannel::class,
            'status' => SubjectRequestStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function historyLabel(): string
    {
        return $this->type->label().' request';
    }
}
