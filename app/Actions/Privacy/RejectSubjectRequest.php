<?php

declare(strict_types=1);

namespace App\Actions\Privacy;

use App\Actions\Action;
use App\Actions\Crm\CloseTask;
use App\Enums\SubjectRequestStatus;
use App\Models\CrmTask;
use App\Models\SubjectRequest;
use App\Models\User;
use App\Support\History\History;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RejectSubjectRequest extends Action
{
    public function __construct(private readonly CloseTask $close) {}

    public function handle(SubjectRequest $request, User $actor, string $verifiedHow, string $outcome): SubjectRequest
    {
        if ($request->status !== SubjectRequestStatus::Open) {
            throw new HttpException(422, 'This request is already closed.');
        }

        return $this->transaction(function () use ($request, $actor, $verifiedHow, $outcome): SubjectRequest {
            $request->forceFill([
                'status' => SubjectRequestStatus::Rejected,
                'verified_how' => $verifiedHow,
                'outcome' => $outcome,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ])->save();

            History::record($request, 'subject_request.closed', after: [
                'type' => $request->type->value,
                'outcome' => $outcome,
            ], actor: $actor);

            $task = CrmTask::query()->where('idempotency_key', 'subject:'.$request->id)->first();

            if ($task instanceof CrmTask) {
                $this->close->autoClose($task, 'the subject request closed');
            }

            return $request->refresh();
        });
    }
}
