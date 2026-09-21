<?php

declare(strict_types=1);

namespace App\Mail\Documents;

use App\Enums\PaymentKind;
use App\Models\Booking;
use App\Models\Delivery;
use App\Models\PaymentLink;
use App\Support\Documents\IssuerMail;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PaymentLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Delivery $delivery,
        public Booking $booking,
        public PaymentLink $link,
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
        $due = $this->link->kind === PaymentKind::Balance
            ? $this->booking->balanceDueDate()->toDateString()
            : null;

        return new Content(
            view: 'mail.documents.payment-link',
            with: [
                'delivery' => $this->delivery,
                'booking' => $this->booking,
                'link' => $this->link,
                'amount' => Money::format($this->link->amount),
                'purpose' => $this->link->kind->label(),
                'dueDate' => $due,
                'replyTo' => IssuerMail::replyTo(),
            ],
        );
    }
}
