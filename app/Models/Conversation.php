<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $contact_id
 * @property string $subject
 * @property Carbon $last_message_at
 * @property ConversationStatus $status
 * @property bool $unread
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $messages_count
 * @property-read Contact|null $contact
 * @property-read Message|null $latestMessage
 * @property-read Message|null $latestInbound
 */
#[Fillable([
    'contact_id',
    'subject',
    'last_message_at',
    'status',
    'unread',
])]
class Conversation extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'status' => ConversationStatus::class,
            'unread' => 'boolean',
        ];
    }

    public function historyLabel(): string
    {
        return $this->subject;
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->ofMany([
            'sent_at' => 'max',
            'id' => 'max',
        ]);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestInbound(): HasOne
    {
        return $this->hasOne(Message::class)->ofMany(
            ['sent_at' => 'max', 'id' => 'max'],
            function (Builder $query): void {
                $query->where('direction', MessageDirection::In->value);
            },
        );
    }

    public function preview(): string
    {
        $latest = $this->latestMessage;
        $text = $latest instanceof Message ? $latest->body_text : '';
        $line = strtok($text, "\n");
        $line = is_string($line) ? trim($line) : '';

        if (mb_strlen($line) <= 160) {
            return $line;
        }

        return mb_substr($line, 0, 157).'…';
    }
}
