<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageDirection;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property MessageDirection $direction
 * @property string $from
 * @property list<string> $to
 * @property string $subject
 * @property string $body_html
 * @property string $body_text
 * @property string|null $message_id
 * @property string|null $in_reply_to
 * @property Carbon $sent_at
 * @property int|null $staff_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Conversation $conversation
 * @property-read User|null $staff
 */
#[Fillable([
    'conversation_id',
    'direction',
    'from',
    'to',
    'subject',
    'body_html',
    'body_text',
    'message_id',
    'in_reply_to',
    'sent_at',
    'staff_id',
])]
class Message extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'to' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function historyLabel(): string
    {
        return $this->subject;
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
