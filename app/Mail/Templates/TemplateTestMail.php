<?php

declare(strict_types=1);

namespace App\Mail\Templates;

use App\Support\Documents\IssuerMail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class TemplateTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $renderedSubject,
        public string $renderedHtml,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
            replyTo: [new Address(IssuerMail::replyTo())],
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->renderedHtml);
    }
}
