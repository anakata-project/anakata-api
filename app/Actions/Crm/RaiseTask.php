<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\Permission;
use App\Enums\TaskKind;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\CrmTask;
use App\Support\History\History;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class RaiseTask extends Action
{
    public function handle(
        TaskKind $kind,
        string $key,
        string $title,
        string $context,
        CarbonInterface $dueAt,
        ?int $ownerId,
        ?Permission $needs,
        ?int $contactId = null,
        ?int $dealId = null,
        ?int $bookingId = null,
        ?int $charterEnquiryId = null,
        ?int $refundRequestId = null,
        ?int $paymentId = null,
        ?int $subjectRequestId = null,
    ): CrmTask {
        $existing = CrmTask::query()->where('idempotency_key', $key)->first();

        if ($existing instanceof CrmTask) {
            return $existing;
        }

        try {
            return $this->transaction(function () use ($kind, $key, $title, $context, $dueAt, $ownerId, $needs, $contactId, $dealId, $bookingId, $charterEnquiryId, $refundRequestId, $paymentId, $subjectRequestId): CrmTask {
                $again = CrmTask::query()->where('idempotency_key', $key)->first();

                if ($again instanceof CrmTask) {
                    return $again;
                }

                $task = CrmTask::query()->create([
                    'title' => $title,
                    'context' => $context,
                    'contact_id' => $contactId,
                    'deal_id' => $dealId,
                    'booking_id' => $bookingId,
                    'charter_enquiry_id' => $charterEnquiryId,
                    'refund_request_id' => $refundRequestId,
                    'payment_id' => $paymentId,
                    'subject_request_id' => $subjectRequestId,
                    'owner_id' => $ownerId,
                    'needs_permission' => $needs?->value,
                    'due_at' => $dueAt,
                    'source' => TaskSource::System,
                    'kind' => $kind,
                    'idempotency_key' => $key,
                    'status' => TaskStatus::Open,
                ]);

                History::record($task, 'task.raised', after: [
                    'kind' => $kind->value,
                    'source' => TaskSource::System->value,
                    'title' => $title,
                ], system: true);

                return $task;
            });
        } catch (UniqueConstraintViolationException) {
            return CrmTask::query()->where('idempotency_key', $key)->firstOrFail();
        }
    }
}
