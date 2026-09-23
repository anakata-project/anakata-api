<?php

declare(strict_types=1);

namespace App\Mail\Crm;

use App\Models\Message;
use App\Support\Documents\IssuerMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

final class ConversationReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Message $record) {}

    public function envelope(): Envelope
    {
        $replyToId = $this->record->in_reply_to ?? '';
        $messageId = trim((string) $this->record->message_id, "<> \t");

        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('mail.from.name')),
            to: $this->record->to,
            subject: $this->record->subject,
            replyTo: [new Address(IssuerMail::replyTo())],
            using: [
                function (Email $email) use ($replyToId, $messageId): void {
                    if ($replyToId !== '') {
                        $email->getHeaders()->addTextHeader('In-Reply-To', $replyToId);
                        $email->getHeaders()->addTextHeader('References', $replyToId);
                    }

                    if ($messageId !== '') {
                        $email->getHeaders()->addIdHeader('Message-ID', $messageId);
                    }
                },
            ],
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->record->body_html);
    }
}
