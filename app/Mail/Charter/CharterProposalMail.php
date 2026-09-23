<?php

declare(strict_types=1);

namespace App\Mail\Charter;

use App\Enums\BookingAccessTokenPurpose;
use App\Models\BookingAccessToken;
use App\Models\Delivery;
use App\Support\Documents\IssuerMail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class CharterProposalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Delivery $delivery,
        public string $pageUrl,
    ) {}

    public static function forDelivery(Delivery $delivery): self
    {
        $parts = explode(':', $delivery->idempotency_key);
        $enquiryId = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        $version = isset($parts[2]) && ctype_digit($parts[2]) ? (int) $parts[2] : 0;

        $pageUrl = BookingAccessToken::query()
            ->where('charter_enquiry_id', $enquiryId)
            ->where('purpose', BookingAccessTokenPurpose::CharterProposal)
            ->whereHas('document', fn ($query) => $query->where('version', $version))
            ->latest('id')
            ->value('page_url');

        return new self($delivery, is_string($pageUrl) ? $pageUrl : '');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->delivery->to,
            subject: $this->delivery->subject,
            replyTo: [new Address(IssuerMail::replyTo())],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.charter.proposal');
    }
}
