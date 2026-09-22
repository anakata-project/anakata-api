<?php

declare(strict_types=1);

namespace App\Mail\Alerts;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class AlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $sentence,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.alerts.alert',
            with: [
                'title' => $this->title,
                'sentence' => $this->sentence,
                'url' => $this->url,
            ],
        );
    }
}
