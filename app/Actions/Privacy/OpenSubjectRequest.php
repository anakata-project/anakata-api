<?php

declare(strict_types=1);

namespace App\Actions\Privacy;

use App\Actions\Action;
use App\Actions\Crm\RaiseTask;
use App\Enums\Permission;
use App\Enums\SubjectRequestChannel;
use App\Enums\SubjectRequestStatus;
use App\Enums\SubjectRequestType;
use App\Enums\TaskKind;
use App\Models\Contact;
use App\Models\SubjectRequest;
use App\Models\User;
use App\Services\Config\CurrentConfig;
use App\Support\History\History;
use Carbon\CarbonInterface;

final class OpenSubjectRequest extends Action
{
    public function __construct(
        private readonly RaiseTask $raise,
        private readonly CurrentConfig $config,
    ) {}

    public function handle(
        Contact $contact,
        SubjectRequestType $type,
        CarbonInterface $receivedAt,
        SubjectRequestChannel $channel,
        User $actor,
        ?string $notes,
    ): SubjectRequest {
        $days = $this->config->businessRules()->privacy->requestSlaDays;

        return $this->transaction(function () use ($contact, $type, $receivedAt, $channel, $actor, $notes, $days): SubjectRequest {
            $request = SubjectRequest::query()->create([
                'contact_id' => $contact->id,
                'type' => $type,
                'received_at' => $receivedAt,
                'due_at' => $receivedAt->copy()->addDays($days),
                'channel' => $channel,
                'status' => SubjectRequestStatus::Open,
            ]);

            History::record($request, 'subject_request.received', after: [
                'type' => $type->value,
                'channel' => $channel->value,
                'notes' => $notes,
            ], actor: $actor);

            $this->raise->handle(
                TaskKind::SubjectRequest,
                'subject:'.$request->id,
                $type->label().' request',
                $type->value.' · privacy SLA',
                $request->due_at,
                $actor->id,
                Permission::PrivacyManage,
                contactId: $contact->id,
                subjectRequestId: $request->id,
            );

            return $request->refresh();
        });
    }
}
