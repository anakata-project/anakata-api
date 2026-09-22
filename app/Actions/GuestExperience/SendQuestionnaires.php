<?php

declare(strict_types=1);

namespace App\Actions\GuestExperience;

use App\Actions\Action;
use App\Actions\Documents\RecordDelivery;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTriggeredBy;
use App\Jobs\SendDeliveryJob;
use App\Models\Booking;
use App\Models\Delivery;
use App\Support\Documents\DeliverySubject;
use App\Support\GuestExperience\QuestionnaireDispatch;
use App\Support\GuestExperience\QuestionnairePlan;

final class SendQuestionnaires extends Action
{
    public function __construct(
        private readonly QuestionnairePlan $plan,
        private readonly RecordDelivery $record,
        private readonly IssueQuestionnaireAccessToken $tokens,
    ) {}

    public function handle(Booking $booking): int
    {
        /** @var int $created */
        $created = $this->transaction(function () use ($booking): int {
            $count = 0;

            foreach ($this->plan->dispatches($booking) as $dispatch) {
                if ($this->recordOne($booking, $dispatch)) {
                    $count++;
                }
            }

            return $count;
        });

        return $created;
    }

    private function recordOne(Booking $booking, QuestionnaireDispatch $dispatch): bool
    {
        $existing = Delivery::query()->where('idempotency_key', $dispatch->key)->first();

        if ($existing instanceof Delivery) {
            return false;
        }

        if ($dispatch->blocked()) {
            $this->record->handle([
                'booking_id' => $booking->id,
                'document_id' => null,
                'kind' => DeliveryKind::Questionnaire,
                'idempotency_key' => $dispatch->key,
                'to' => [],
                'cc' => [],
                'subject' => DeliverySubject::forDocument(DeliveryKind::Questionnaire, $booking),
                'status' => DeliveryStatus::Blocked,
                'blocked_reason' => $dispatch->blockedReason,
                'triggered_by' => DeliveryTriggeredBy::System,
            ]);

            return true;
        }

        $this->tokens->handle($booking, $dispatch->guestId, $dispatch->coveredGuestIds);

        $delivery = $this->record->handle([
            'booking_id' => $booking->id,
            'document_id' => null,
            'kind' => DeliveryKind::Questionnaire,
            'idempotency_key' => $dispatch->key,
            'to' => $dispatch->to,
            'cc' => [],
            'subject' => DeliverySubject::forDocument(DeliveryKind::Questionnaire, $booking),
            'status' => DeliveryStatus::Queued,
            'triggered_by' => DeliveryTriggeredBy::System,
            'blocked_reason' => null,
        ]);

        if ($delivery->wasRecentlyCreated && $delivery->status === DeliveryStatus::Queued) {
            SendDeliveryJob::dispatch($delivery->id)->afterCommit();
        }

        return true;
    }
}
