<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityKind;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contact_id
 * @property ActivityKind $kind
 * @property string $body
 * @property Carbon $occurred_at
 * @property int|null $deal_id
 * @property int|null $crm_task_id
 */
#[Fillable([
    'contact_id',
    'kind',
    'body',
    'occurred_at',
    'deal_id',
    'crm_task_id',
])]
class ContactActivity extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ActivityKind::class,
            'occurred_at' => 'datetime',
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
        return $this->kind->label();
    }
}
