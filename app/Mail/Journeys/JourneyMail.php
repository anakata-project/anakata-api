<?php

declare(strict_types=1);

namespace App\Mail\Journeys;

use App\Models\Delivery;
use App\Models\JourneySend;
use App\Models\MessageTemplateVersion;
use App\Support\Documents\IssuerMail;
use App\Support\Templates\TemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

final class JourneyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Delivery $delivery) {}

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
        $send = JourneySend::query()
            ->where('delivery_id', $this->delivery->id)
            ->with(['enrolment.contact', 'enrolment.booking'])
            ->first();

        $version = $send instanceof JourneySend && $send->template_version !== null
            ? MessageTemplateVersion::query()
                ->where('version', $send->template_version)
                ->whereHas('template', fn ($query) => $query->where('key', $send->template_key))
                ->first()
            : null;

        if (! $send instanceof JourneySend || ! $version instanceof MessageTemplateVersion) {
            throw new InvalidArgumentException('A journey delivery is missing its template version.');
        }

        $rendered = app(TemplateRenderer::class)->render(
            $version,
            $send->enrolment->contact,
            $send->enrolment->booking,
        );

        return new Content(htmlString: $rendered->html);
    }
}
