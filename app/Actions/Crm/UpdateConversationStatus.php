<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Support\History\History;

final class UpdateConversationStatus extends Action
{
    public function handle(Conversation $conversation, ConversationStatus $status, User $actor): Conversation
    {
        return $this->transaction(function () use ($conversation, $status, $actor): Conversation {
            if ($conversation->status === $status) {
                return $conversation;
            }

            $before = $conversation->status->value;
            $conversation->status = $status;
            $conversation->save();

            History::record(
                $conversation,
                'conversation.status_changed',
                before: ['status' => $before],
                after: ['status' => $status->value],
                actor: $actor,
            );

            return $conversation->fresh() ?? $conversation;
        });
    }
}
