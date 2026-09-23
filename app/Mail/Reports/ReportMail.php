<?php

declare(strict_types=1);

namespace App\Mail\Reports;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $sentence,
        public string $url,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.reports.report',
            with: [
                'title' => $this->title,
                'sentence' => $this->sentence,
                'url' => $this->url,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->attachmentPath === null || $this->attachmentName === null) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('reports', $this->attachmentPath)->as($this->attachmentName),
        ];
    }
}
