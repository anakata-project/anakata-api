<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class LinkConversationContact extends Action
{
    public function handle(Conversation $conversation, Contact $contact, User $actor): Conversation
    {
        return $this->transaction(function () use ($conversation, $contact, $actor): Conversation {
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);

            if ($conversation->contact_id !== null) {
                throw ValidationException::withMessages([
                    'contact_id' => 'This conversation is already linked to a contact.',
                ]);
            }

            $contact = $contact->currentSurvivor();
            $existing = Conversation::query()
                ->where('contact_id', $contact->id)
                ->where('subject', $conversation->subject)
                ->whereKeyNot($conversation->id)
                ->lockForUpdate()
                ->first();

            if (! $existing instanceof Conversation) {
                $conversation->contact_id = $contact->id;
                $conversation->save();

                History::record(
                    $conversation,
                    'conversation.linked',
                    before: ['contact_id' => null],
                    after: ['contact_id' => $contact->id],
                    actor: $actor,
                );

                return $conversation->fresh() ?? $conversation;
            }

            Message::query()->where('conversation_id', $conversation->id)->update([
                'conversation_id' => $existing->id,
                'updated_at' => now(),
                'updated_by' => $actor->id,
            ]);

            if ($conversation->last_message_at->greaterThan($existing->last_message_at)) {
                $existing->last_message_at = $conversation->last_message_at;
            }

            if ($conversation->unread) {
                $existing->unread = true;
            }

            $existing->save();
            $absorbedId = $conversation->id;
            $conversation->delete();

            History::record(
                $existing,
                'conversation.linked',
                before: ['contact_id' => $contact->id],
                after: [
                    'contact_id' => $contact->id,
                    'absorbed_conversation_id' => $absorbedId,
                ],
                actor: $actor,
            );

            return $existing->fresh() ?? $existing;
        });
    }
}
