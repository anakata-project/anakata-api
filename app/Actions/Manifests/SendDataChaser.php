<?php

declare(strict_types=1);

namespace App\Actions\Manifests;

use App\Actions\Action;
use App\Actions\Complete\IssueCompleteAccessToken;
use App\Actions\Documents\RecordDelivery;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTriggeredBy;
use App\Jobs\SendDeliveryJob;
use App\Models\Booking;
use App\Models\Delivery;
use App\Support\Documents\DeliverySubject;
use App\Support\Documents\Recipients;

final class SendDataChaser extends Action
{
    public function __construct(
        private readonly Recipients $recipients,
        private readonly RecordDelivery $record,
        private readonly IssueCompleteAccessToken $tokens,
    ) {}

    public function handle(Booking $booking): Delivery
    {
        /** @var Delivery $delivery */
        $delivery = $this->transaction(function () use ($booking): Delivery {
            $booking->loadMissing('departure');
            $key = 'chase:'.$booking->id.':'.$booking->departure_id;
            $existing = Delivery::query()->where('idempotency_key', $key)->first();

            if ($existing instanceof Delivery) {
                return $existing;
            }

            $resolved = $this->recipients->resolve($booking, DeliveryKind::DataChaser);

            if (! $resolved->usable()) {
                return $this->record->handle([
                    'booking_id' => $booking->id,
                    'document_id' => null,
                    'kind' => DeliveryKind::DataChaser,
                    'idempotency_key' => $key,
                    'to' => [],
                    'cc' => [],
                    'subject' => DeliverySubject::forDocument(DeliveryKind::DataChaser, $booking),
                    'status' => DeliveryStatus::Blocked,
                    'blocked_reason' => $resolved->blockedReason,
                    'triggered_by' => DeliveryTriggeredBy::System,
                ]);
            }

            $this->tokens->handle($booking);

            $delivery = $this->record->handle([
                'booking_id' => $booking->id,
                'document_id' => null,
                'kind' => DeliveryKind::DataChaser,
                'idempotency_key' => $key,
                'to' => $resolved->to,
                'cc' => $resolved->cc,
                'subject' => DeliverySubject::forDocument(DeliveryKind::DataChaser, $booking),
                'status' => DeliveryStatus::Queued,
                'triggered_by' => DeliveryTriggeredBy::System,
                'blocked_reason' => null,
            ]);

            if ($delivery->wasRecentlyCreated && $delivery->status === DeliveryStatus::Queued) {
                SendDeliveryJob::dispatch($delivery->id)->afterCommit();
            }

            return $delivery;
        });

        return $delivery;
    }
}
