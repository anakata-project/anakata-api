<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Actions\Action;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTriggeredBy;
use App\Jobs\SendDeliveryJob;
use App\Models\Booking;
use App\Models\Delivery;
use App\Models\PaymentLink;
use App\Models\User;
use App\Support\Documents\DeliveryKey;
use App\Support\Documents\DeliverySubject;
use App\Support\Documents\Recipients;
use InvalidArgumentException;

final class SendPaymentRequest extends Action
{
    public function __construct(
        private readonly Recipients $recipients,
        private readonly RecordDelivery $record,
    ) {}

    public function handle(
        Booking $booking,
        DeliveryKind $kind,
        ?PaymentLink $link = null,
        ?int $reminderDays = null,
        ?User $actor = null,
        bool $system = false,
        bool $resend = false,
    ): Delivery {
        if ($kind !== DeliveryKind::PaymentLink && $kind !== DeliveryKind::Reminder) {
            throw new InvalidArgumentException('SendPaymentRequest only sends payment links and reminders.');
        }

        return $this->transaction(function () use ($booking, $kind, $link, $reminderDays, $actor, $system, $resend): Delivery {
            $recipients = $this->recipients->resolve($booking, $kind);
            $firstKey = $this->firstKey($booking, $kind, $link, $reminderDays);
            $triggeredBy = $system || ! $actor instanceof User
                ? DeliveryTriggeredBy::System
                : DeliveryTriggeredBy::User;
            $subject = $kind === DeliveryKind::Reminder
                ? DeliverySubject::forReminder($booking)
                : DeliverySubject::forPaymentLink($booking, $link ?? throw new InvalidArgumentException('A payment-link send needs a link.'));

            if (! $recipients->usable()) {
                return $this->record->handle([
                    'booking_id' => $booking->id,
                    'document_id' => null,
                    'kind' => $kind,
                    'idempotency_key' => DeliveryKey::blocked($firstKey),
                    'to' => [],
                    'cc' => [],
                    'subject' => $subject,
                    'status' => DeliveryStatus::Blocked,
                    'blocked_reason' => $recipients->blockedReason,
                    'triggered_by' => $triggeredBy,
                ]);
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

                $key = $kind === DeliveryKind::Reminder
                    ? DeliveryKey::resendReminder(
                        $booking->id,
                        $booking->balanceDueDate()->toDateString(),
                        (int) $reminderDays,
                    )
                    : DeliveryKey::resendPaymentLink($link->id);
            }

            $delivery = $this->record->handle([
                'booking_id' => $booking->id,
                'document_id' => null,
                'kind' => $kind,
                'idempotency_key' => $key,
                'to' => $recipients->to,
                'cc' => $recipients->cc,
                'subject' => $subject,
                'status' => DeliveryStatus::Queued,
                'triggered_by' => $triggeredBy,
            ]);

            if ($delivery->wasRecentlyCreated && $delivery->status === DeliveryStatus::Queued) {
                SendDeliveryJob::dispatch($delivery->id)->afterCommit();
            }

            return $delivery;
        });
    }

    private function firstKey(Booking $booking, DeliveryKind $kind, ?PaymentLink $link, ?int $reminderDays): string
    {
        if ($kind === DeliveryKind::Reminder) {
            if ($reminderDays === null) {
                throw new InvalidArgumentException('A reminder send needs the number of days.');
            }

            return DeliveryKey::forReminder(
                $booking->id,
                $booking->balanceDueDate()->toDateString(),
                $reminderDays,
            );
        }

        if (! $link instanceof PaymentLink) {
            throw new InvalidArgumentException('A payment-link send needs a link.');
        }

        return DeliveryKey::forPaymentLink($link->id);
    }
}
