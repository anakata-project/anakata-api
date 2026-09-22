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

final class DataChaserMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Delivery $delivery,
        public Booking $booking,
        public string $completeUrl,
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
            view: 'mail.documents.data-chaser',
            with: [
                'delivery' => $this->delivery,
                'booking' => $this->booking,
                'completeUrl' => $this->completeUrl,
                'replyTo' => IssuerMail::replyTo(),
            ],
        );
    }
}
