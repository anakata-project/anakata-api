<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTriggeredBy;
use App\Events\DeliveryOutcomeRecorded;
use App\Mail\Documents\DeliveryMailFactory;
use App\Models\Booking;
use App\Models\Delivery;
use App\Models\User;
use App\Support\Automations\AutomationCatalogue;
use App\Support\Automations\AutomationGate;
use App\Support\History\History;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class SendDeliveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $deliveryId) {}

    public function handle(AutomationGate $gate): void
    {
        $delivery = Delivery::query()
            ->with(['booking', 'document'])
            ->find($this->deliveryId);

        if (! $delivery instanceof Delivery || $delivery->status === DeliveryStatus::Sent) {
            return;
        }

        if ($delivery->status !== DeliveryStatus::Queued) {
            return;
        }

        $key = AutomationCatalogue::keyForDelivery($delivery);

        if (is_string($key) && ! $gate->allows($key)) {
            $gate->blockDelivery($delivery, $key);

            return;
        }

        $pdfBytes = $this->pdfBytes($delivery);

        Mail::send(DeliveryMailFactory::make($delivery, $pdfBytes));

        DB::transaction(function () use ($delivery): void {
            $fresh = Delivery::query()->findOrFail($delivery->id);

            if ($fresh->status === DeliveryStatus::Sent) {
                return;
            }

            $fresh->status = DeliveryStatus::Sent;
            $fresh->sent_at = now();
            $fresh->error = null;
            $fresh->save();

            $this->writeHistory($fresh, sent: true);
            DeliveryOutcomeRecorded::dispatch($fresh);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = Delivery::query()->find($this->deliveryId);

        if (! $delivery instanceof Delivery || $delivery->status === DeliveryStatus::Sent) {
            return;
        }

        DB::transaction(function () use ($delivery, $exception): void {
            $fresh = Delivery::query()->findOrFail($delivery->id);

            if ($fresh->status === DeliveryStatus::Sent) {
                return;
            }

            $fresh->status = DeliveryStatus::Failed;
            $fresh->error = $exception?->getMessage() ?: 'Send failed';
            $fresh->save();

            $this->writeHistory($fresh, sent: false);
            DeliveryOutcomeRecorded::dispatch($fresh);
        });
    }

    private function pdfBytes(Delivery $delivery): ?string
    {
        if (! $delivery->kind->attachesPdf()) {
            return null;
        }

        $document = $delivery->document;

        if ($document === null) {
            throw new RuntimeException('A document delivery is missing its document.');
        }

        $bytes = Storage::disk('documents')->get($document->file_path);

        if (! is_string($bytes)) {
            throw new RuntimeException('The stored PDF for this delivery is missing.');
        }

        return $bytes;
    }

    private function writeHistory(Delivery $delivery, bool $sent): void
    {
        $booking = $delivery->booking;

        if (! $booking instanceof Booking) {
            return;
        }

        $documentKind = $delivery->kind->isDocumentKind();
        $event = $sent
            ? ($documentKind ? 'document.sent' : 'payment_request.sent')
            : ($documentKind ? 'document.send_failed' : 'payment_request.send_failed');

        $system = $delivery->triggered_by === DeliveryTriggeredBy::System;
        $actor = $system ? null : User::query()->find($delivery->created_by);

        History::record($booking, $event, after: [
            'kind' => $delivery->kind->value,
            'delivery_id' => $delivery->id,
            'status' => $delivery->status->value,
            'error' => $delivery->error,
        ], actor: $actor, system: $system, extraContext: [
            'what' => $delivery->kind->label().($sent ? ' sent' : ' send failed'),
        ]);
    }
}
