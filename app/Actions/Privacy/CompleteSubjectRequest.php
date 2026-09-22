<?php

declare(strict_types=1);

namespace App\Actions\Privacy;

use App\Actions\Action;
use App\Actions\Crm\CloseTask;
use App\Actions\Crm\RecordContactConsent;
use App\Enums\ConsentCapturePoint;
use App\Enums\ConsentPurpose;
use App\Enums\SubjectRequestStatus;
use App\Enums\SubjectRequestType;
use App\Models\CrmTask;
use App\Models\SubjectRequest;
use App\Models\User;
use App\Services\Config\CurrentConfig;
use App\Support\Crm\ConsentVersionForPurpose;
use App\Support\History\History;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CompleteSubjectRequest extends Action
{
    public function __construct(
        private readonly CloseTask $close,
        private readonly RecordContactConsent $consents,
        private readonly CurrentConfig $config,
    ) {}

    public function handle(SubjectRequest $request, User $actor, string $verifiedHow, string $outcome): SubjectRequest
    {
        $this->assertOpen($request);

        if ($request->type === SubjectRequestType::Erasure) {
            throw new HttpException(422, 'Erasure uses the erase action.');
        }

        return $this->transaction(function () use ($request, $actor, $verifiedHow, $outcome): SubjectRequest {
            if ($request->type === SubjectRequestType::Objection) {
                $this->withdraw($request, $actor);
            }

            $request->forceFill([
                'status' => SubjectRequestStatus::Completed,
                'verified_how' => $verifiedHow,
                'outcome' => $outcome,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ])->save();

            History::record($request, 'subject_request.closed', after: [
                'type' => $request->type->value,
                'outcome' => $outcome,
            ], actor: $actor);

            $this->closeTask($request);

            return $request->refresh();
        });
    }

    private function withdraw(SubjectRequest $request, User $actor): void
    {
        $privacy = $this->config->businessRules()->consentVersions->privacy;
        $request->loadMissing('contact');

        foreach ([ConsentPurpose::Marketing, ConsentPurpose::Profiling, ConsentPurpose::Remarketing] as $purpose) {
            $version = ConsentVersionForPurpose::current($purpose) ?? $privacy;

            $this->consents->handle(
                $request->contact,
                $purpose,
                false,
                $version,
                ConsentCapturePoint::SubjectRequest,
                recordedBy: $actor,
            );
        }
    }

    private function assertOpen(SubjectRequest $request): void
    {
        if ($request->status !== SubjectRequestStatus::Open) {
            throw new HttpException(422, 'This request is already closed.');
        }
    }

    private function closeTask(SubjectRequest $request): void
    {
        $task = CrmTask::query()->where('idempotency_key', 'subject:'.$request->id)->first();

        if ($task instanceof CrmTask) {
            $this->close->autoClose($task, 'the subject request closed');
        }
    }
}
