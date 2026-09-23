<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Support\Iso;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * @mixin Conversation
 */
#[SchemaName('CrmConversationResource')]
class CrmConversationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     contact_id: int|null,
     *     contact_name: string|null,
     *     from: string|null,
     *     subject: string,
     *     preview: string,
     *     unread: bool,
     *     message_count: int,
     *     status: ConversationStatus,
     *     last_message_at: string|null,
     *     messages?: list<CrmMessageResource>
     * }
     *
     * @phpstan-return array{
     *     id: int,
     *     contact_id: int|null,
     *     contact_name: string|null,
     *     from: string|null,
     *     subject: string,
     *     preview: string,
     *     unread: bool,
     *     message_count: int,
     *     status: ConversationStatus,
     *     last_message_at: string|null,
     *     messages?: AnonymousResourceCollection
     * }
     */
    public function toArray(Request $request): array
    {
        $conversation = $this->resource;

        if (! $conversation instanceof Conversation) {
            throw new LogicException('Conversation resource expected a conversation.');
        }

        $row = [
            'id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'contact_name' => $conversation->contact?->name,
            'from' => $conversation->latestInbound?->from,
            'subject' => $conversation->subject,
            'preview' => $conversation->preview(),
            'unread' => $conversation->unread,
            'message_count' => $this->messageCount($conversation),
            'status' => $this->status($conversation),
            'last_message_at' => Iso::utc($conversation->last_message_at),
        ];

        if ($conversation->relationLoaded('messages')) {
            $row['messages'] = CrmMessageResource::collection($conversation->messages);
        }

        return $row;
    }

    private function status(Conversation $conversation): ConversationStatus
    {
        return $conversation->status;
    }

    private function messageCount(Conversation $conversation): int
    {
        if ($conversation->messages_count !== null) {
            return $conversation->messages_count;
        }

        if ($conversation->relationLoaded('messages')) {
            return $conversation->messages->count();
        }

        return $conversation->messages()->count();
    }
}
