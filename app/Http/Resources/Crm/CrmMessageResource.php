<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\Message;
use App\Support\Iso;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * @mixin Message
 */
#[SchemaName('CrmMessageResource')]
class CrmMessageResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     direction: string,
     *     from: string,
     *     to: list<string>,
     *     subject: string,
     *     body_html: string,
     *     body_text: string,
     *     message_id: string|null,
     *     in_reply_to: string|null,
     *     sent_at: string|null,
     *     staff_id: int|null
     * }
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;

        if (! $message instanceof Message) {
            throw new LogicException('Message resource expected a message.');
        }

        return [
            'id' => $message->id,
            'direction' => $message->direction->value,
            'from' => $message->from,
            'to' => $message->to,
            'subject' => $message->subject,
            'body_html' => $message->body_html,
            'body_text' => $message->body_text,
            'message_id' => $message->message_id,
            'in_reply_to' => $message->in_reply_to,
            'sent_at' => Iso::utc($message->sent_at),
            'staff_id' => $message->staff_id,
        ];
    }
}
