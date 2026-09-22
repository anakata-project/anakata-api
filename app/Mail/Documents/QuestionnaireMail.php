<?php

declare(strict_types=1);

namespace App\Mail\Documents;

use App\Models\Booking;
use App\Models\Delivery;
use App\Support\Documents\IssuerMail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class QuestionnaireMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Delivery $delivery,
        public Booking $booking,
        public string $questionnaireUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->delivery->to,
            cc: $this->delivery->cc,
            subject: $this->delivery->subject,
            replyTo: [new Address(IssuerMail::replyTo())],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.documents.questionnaire',
            with: [
                'delivery' => $this->delivery,
                'booking' => $this->booking,
                'questionnaireUrl' => $this->questionnaireUrl,
                'replyTo' => IssuerMail::replyTo(),
            ],
        );
    }
}
