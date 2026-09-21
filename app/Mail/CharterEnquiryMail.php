<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CharterEnquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class CharterEnquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CharterEnquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Charter enquiry · '.$this->enquiry->contact->name,
        );
    }

    public function content(): Content
    {
        $this->enquiry->loadMissing(['contact', 'departure']);

        return new Content(
            markdown: 'mail.charter-enquiry',
            with: [
                'enquiry' => $this->enquiry,
            ],
        );
    }
}
