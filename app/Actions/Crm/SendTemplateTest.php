<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\AlertNotificationStatus;
use App\Mail\Templates\TemplateTestMail;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Models\MessageTemplateVersion;
use App\Models\TemplateTestSend;
use App\Models\User;
use App\Support\History\History;
use App\Support\Templates\TemplateRenderer;
use App\Support\Templates\TemplateVariableException;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SendTemplateTest extends Action
{
    public function __construct(private readonly TemplateRenderer $renderer) {}

    public function handle(
        MessageTemplate $template,
        MessageTemplateVersion $version,
        Contact $contact,
        ?Booking $booking,
        User $actor,
    ): TemplateTestSend {
        if ($version->template_id !== $template->id) {
            throw new HttpException(404, 'That template version does not exist.');
        }

        try {
            $rendered = $this->renderer->render($version, $contact, $booking);
        } catch (TemplateVariableException $exception) {
            throw new HttpException(422, $exception->getMessage());
        }

        $row = $this->transaction(function () use ($template, $version, $contact, $booking, $actor, $rendered): TemplateTestSend {
            Mail::to($actor->email)->send(new TemplateTestMail($rendered->subject, $rendered->html));

            $row = TemplateTestSend::query()->create([
                'message_template_version_id' => $version->id,
                'user_id' => $actor->id,
                'contact_id' => $contact->id,
                'booking_id' => $booking?->id,
                'status' => AlertNotificationStatus::Sent,
                'sent_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            History::record(
                $template,
                'template.test_sent',
                after: [
                    'version' => $version->version,
                    'user_id' => $actor->id,
                ],
                actor: $actor,
            );

            return $row;
        });

        $row->setAttribute('rendered_subject', $rendered->subject);

        return $row;
    }
}
