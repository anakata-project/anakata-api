<?php

declare(strict_types=1);

namespace App\Mail\Documents;

use App\Enums\DeliveryKind;
use App\Models\Delivery;
use App\Models\Document;
use App\Support\Documents\IssuerMail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class DocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Delivery $delivery,
        public Document $document,
        public string $pdfBytes,
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
        $view = match ($this->delivery->kind) {
            DeliveryKind::Invoice => 'mail.documents.invoice',
            DeliveryKind::FinalInvoice => 'mail.documents.final-invoice',
            DeliveryKind::Summary => 'mail.documents.summary',
            DeliveryKind::Receipt => 'mail.documents.receipt',
            DeliveryKind::Voucher => 'mail.documents.voucher',
            DeliveryKind::Pretrip => 'mail.documents.pretrip',
            DeliveryKind::WireInstructions => 'mail.documents.wire-instructions',
            default => 'mail.documents.invoice',
        };

        return new Content(
            view: $view,
            with: [
                'delivery' => $this->delivery,
                'document' => $this->document,
                'booking' => $this->delivery->booking,
                'replyTo' => IssuerMail::replyTo(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $filename = $this->document->kind->value.'-v'.$this->document->version.'.pdf';

        return [
            Attachment::fromData(fn (): string => $this->pdfBytes, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
