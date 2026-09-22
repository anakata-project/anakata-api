<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Actions\Documents\SendDocument;
use App\Actions\Documents\SendPaymentRequest;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\PaymentLink;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RetryFailedDelivery extends Action
{
    public function __construct(
        private readonly SendDocument $documents,
        private readonly SendPaymentRequest $paymentRequests,
    ) {}

    public function handle(Delivery $delivery, User $actor): Delivery
    {
        if ($delivery->status !== DeliveryStatus::Failed) {
            throw new HttpException(422, 'Only FAILED deliveries can be retried.');
        }

        $booking = $delivery->booking;

        if ($delivery->kind === DeliveryKind::PaymentLink) {
            $link = PaymentLink::query()
                ->where('booking_id', $booking->id)
                ->orderByDesc('id')
                ->first();

            if (! $link instanceof PaymentLink) {
                throw new HttpException(422, 'That payment-link delivery has no link to resend.');
            }

            return $this->paymentRequests->handle($booking, DeliveryKind::PaymentLink, $link, actor: $actor, resend: true);
        }

        if ($delivery->kind === DeliveryKind::Reminder) {
            return $this->paymentRequests->handle($booking, DeliveryKind::Reminder, reminderDays: 1, actor: $actor, resend: true);
        }

        if ($delivery->kind === DeliveryKind::DataChaser || $delivery->kind === DeliveryKind::Questionnaire) {
            throw new HttpException(422, 'That delivery has no document to resend.');
        }

        $document = $delivery->document;

        if (! $document instanceof Document) {
            $kind = $delivery->kind->documentKind();

            if ($kind === null) {
                throw new HttpException(422, 'That delivery has no document to resend.');
            }

            $document = Document::query()
                ->where('booking_id', $booking->id)
                ->where('kind', $kind)
                ->orderByDesc('version')
                ->first();
        }

        if (! $document instanceof Document) {
            throw new HttpException(422, 'That delivery has no document to resend.');
        }

        return $this->documents->handle($booking, $document, $actor, resend: true);
    }
}
