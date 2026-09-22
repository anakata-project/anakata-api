<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Actions\Action;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTriggeredBy;
use App\Events\DeliveryOutcomeRecorded;
use App\Jobs\SendDeliveryJob;
use App\Models\Booking;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\User;
use App\Support\Documents\DeliveryKey;
use App\Support\Documents\DeliverySubject;
use App\Support\Documents\Recipients;

final class SendDocument extends Action
{
    public function __construct(
        private readonly Recipients $recipients,
        private readonly RecordDelivery $record,
    ) {}

    public function handle(
        Booking $booking,
        Document $document,
        ?User $actor = null,
        bool $system = false,
        bool $resend = false,
        ?string $idempotencyKey = null,
    ): Delivery {
        return $this->transaction(function () use ($booking, $document, $actor, $system, $resend, $idempotencyKey): Delivery {
            $kind = DeliveryKind::fromDocument($document->kind);
            $recipients = $this->recipients->resolve($booking, $kind);
            $firstKey = $idempotencyKey ?? DeliveryKey::forDocument($document);
            $triggeredBy = $system || ! $actor instanceof User
                ? DeliveryTriggeredBy::System
                : DeliveryTriggeredBy::User;

            if (! $recipients->usable()) {
                $delivery = $this->record->handle([
                    'booking_id' => $booking->id,
                    'document_id' => $document->id,
                    'kind' => $kind,
                    'idempotency_key' => DeliveryKey::blocked($firstKey),
                    'to' => [],
                    'cc' => [],
                    'subject' => DeliverySubject::forDocument($kind, $booking),
                    'status' => DeliveryStatus::Blocked,
                    'blocked_reason' => $recipients->blockedReason,
                    'triggered_by' => $triggeredBy,
                ]);

                if ($delivery->wasRecentlyCreated) {
                    DeliveryOutcomeRecorded::dispatch($delivery);
                }

                return $delivery;
            }

            $existing = Delivery::query()->where('idempotency_key', $firstKey)->first();

            if ($existing instanceof Delivery && $existing->status === DeliveryStatus::Queued) {
                return $existing;
            }

            $key = $firstKey;

            if ($resend || ($existing instanceof Delivery && in_array($existing->status, [DeliveryStatus::Sent, DeliveryStatus::Failed], true))) {
                if (! $resend) {
                    return $existing;
                }

                $key = DeliveryKey::resendDocument($document->id);
            }

            $delivery = $this->record->handle([
                'booking_id' => $booking->id,
                'document_id' => $document->id,
                'kind' => $kind,
                'idempotency_key' => $key,
                'to' => $recipients->to,
                'cc' => $recipients->cc,
                'subject' => DeliverySubject::forDocument($kind, $booking),
                'status' => DeliveryStatus::Queued,
                'triggered_by' => $triggeredBy,
            ]);

            if ($delivery->wasRecentlyCreated && $delivery->status === DeliveryStatus::Queued) {
                SendDeliveryJob::dispatch($delivery->id)->afterCommit();
            }

            return $delivery;
        });
    }
}
