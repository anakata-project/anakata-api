<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\History\History;
use App\Support\Mail\InboundMail;
use App\Support\Mail\InboundMessageId;
use App\Support\Mail\QuotedReply;
use App\Support\Mail\SubjectThread;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

final class CaptureInboundMessage extends Action
{
    public function handle(InboundMail $mail): Message
    {
        try {
            return $this->transaction(fn (): Message => $this->capture($mail));
        } catch (UniqueConstraintViolationException $exception) {
            $messageId = InboundMessageId::canonical(
                $mail->messageId,
                $mail->from,
                $mail->sentAt,
                $mail->subject,
                $mail->bodyHtml,
            );
            $existing = Message::query()->where('message_id', $messageId)->first();

            if ($existing instanceof Message) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function capture(InboundMail $mail): Message
    {
        $messageId = InboundMessageId::canonical(
            $mail->messageId,
            $mail->from,
            $mail->sentAt,
            $mail->subject,
            $mail->bodyHtml,
        );
        $existing = Message::query()->where('message_id', $messageId)->lockForUpdate()->first();

        if ($existing instanceof Message) {
            return $existing;
        }

        $rawText = $mail->bodyText !== '' ? $mail->bodyText : QuotedReply::fromHtml($mail->bodyHtml);
        $bodyText = QuotedReply::strip($rawText);
        $contact = $this->contactFor($mail->from);
        $conversation = $this->thread($mail, $contact);
        $created = ! $conversation->exists;
        $replyTo = $mail->inReplyTo ?? ($mail->references[0] ?? null);

        if ($created) {
            $conversation->save();
        }

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::In,
            'from' => $mail->from,
            'to' => $mail->to,
            'subject' => mb_substr($mail->subject, 0, 500),
            'body_html' => $mail->bodyHtml,
            'body_text' => $bodyText,
            'message_id' => $messageId,
            'in_reply_to' => $replyTo,
            'sent_at' => $mail->sentAt,
            'staff_id' => null,
        ]);

        if ($conversation->last_message_at->lessThan(Carbon::parse($mail->sentAt))) {
            $conversation->last_message_at = Carbon::parse($mail->sentAt);
        }

        $conversation->unread = true;
        $conversation->save();

        History::record($conversation, 'conversation.message', after: [
            'direction' => MessageDirection::In->value,
            'message_id' => $messageId,
            'subject' => $conversation->subject,
        ], system: true);

        return $message;
    }

    private function contactFor(string $from): ?Contact
    {
        $email = Contact::normalizeEmail($from);

        if ($email === null) {
            return null;
        }

        $contact = Contact::query()->where('email', $email)->first();

        return $contact?->currentSurvivor();
    }

    private function thread(InboundMail $mail, ?Contact $contact): Conversation
    {
        $candidates = $mail->references;

        if ($mail->inReplyTo !== null) {
            array_unshift($candidates, $mail->inReplyTo);
        }

        $candidates = array_values(array_unique($candidates));

        if ($candidates !== []) {
            $matched = Message::query()
                ->whereIn('message_id', $candidates)
                ->orderByDesc('sent_at')
                ->orderByDesc('id')
                ->first();

            if ($matched instanceof Message) {
                return $matched->conversation;
            }
        }

        $subject = SubjectThread::normalise($mail->subject);
        $query = Conversation::query()->where('subject', $subject);

        if ($contact instanceof Contact) {
            $query->where('contact_id', $contact->id);
        } else {
            $query->whereNull('contact_id')
                ->whereHas('messages', function (Builder $messages) use ($mail): void {
                    $messages->where('direction', MessageDirection::In->value)
                        ->where('from', $mail->from);
                });
        }

        $existing = $query->orderByDesc('last_message_at')->orderByDesc('id')->first();

        if ($existing instanceof Conversation) {
            return $existing;
        }

        $conversation = new Conversation([
            'contact_id' => $contact?->id,
            'subject' => $subject,
            'last_message_at' => Carbon::parse($mail->sentAt),
            'status' => ConversationStatus::Open,
            'unread' => true,
        ]);

        return $conversation;
    }
}
