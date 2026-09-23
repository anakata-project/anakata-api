<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Models\Conversation;
use App\Models\User;
use App\Support\History\History;

final class MarkConversationRead extends Action
{
    public function handle(Conversation $conversation, User $actor): Conversation
    {
        return $this->transaction(function () use ($conversation, $actor): Conversation {
            if (! $conversation->unread) {
                return $conversation;
            }

            $conversation->unread = false;
            $conversation->save();

            History::record(
                $conversation,
                'conversation.read',
                before: ['unread' => true],
                after: ['unread' => false],
                actor: $actor,
            );

            return $conversation->fresh() ?? $conversation;
        });
    }
}
