<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTriggeredBy;
use App\Events\DeliveryOutcomeRecorded;
use App\Mail\Documents\DeliveryMailFactory;
use App\Models\Booking;
use App\Models\ChangeHistory;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\User;
use App\Support\Automations\AutomationCatalogue;
use App\Support\Automations\AutomationGate;
use App\Support\Deliveries\BounceClassifier;
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

        try {
            Mail::send(DeliveryMailFactory::make($delivery, $pdfBytes));
        } catch (Throwable $exception) {
            if (! BounceClassifier::isHard($exception)) {
                throw $exception;
            }

            $this->recordHardBounce($delivery, $exception);

            return;
        }

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

    private function recordHardBounce(Delivery $delivery, Throwable $exception): void
    {
        DB::transaction(function () use ($delivery, $exception): void {
            $fresh = Delivery::query()->with('booking')->findOrFail($delivery->id);

            if (in_array($fresh->status, [DeliveryStatus::Sent, DeliveryStatus::HardBounce], true)) {
                return;
            }

            $fresh->status = DeliveryStatus::HardBounce;
            $fresh->error = $exception->getMessage();
            $fresh->save();

            $contact = $this->contactFor($fresh);

            if (! $contact instanceof Contact) {
                return;
            }

            $seen = ChangeHistory::query()
                ->where('subject_type', $contact->getMorphClass())
                ->where('subject_id', $contact->id)
                ->where('event', 'contact.suppressed')
                ->exists();

            if ($seen) {
                return;
            }

            History::record($contact, 'contact.suppressed', reason: 'HARD_BOUNCE', after: [
                'reason' => 'HARD_BOUNCE',
                'delivery_id' => $fresh->id,
            ], system: true);
        });
    }

    private function contactFor(Delivery $delivery): ?Contact
    {
        $booking = $delivery->booking;

        if ($booking instanceof Booking) {
            $contact = Contact::query()->find($booking->contact_id);

            if ($contact instanceof Contact) {
                return $contact;
            }
        }

        foreach ($delivery->to as $address) {
            $email = Contact::normalizeEmail($address);

            if ($email === null) {
                continue;
            }

            $contact = Contact::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

            if ($contact instanceof Contact) {
                return $contact;
            }
        }

        return null;
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
        if ($delivery->kind === DeliveryKind::Journey) {
            return;
        }

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
