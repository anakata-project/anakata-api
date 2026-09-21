<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Actions\Documents\PrepareIssueDocument;
use App\Actions\Documents\SendDocument;
use App\Enums\DocumentKind;
use App\Models\Booking;
use App\Models\Document;

final class IssueOnce
{
    public function __construct(
        private readonly PrepareIssueDocument $prepare,
        private readonly SendDocument $send,
    ) {}

    public function handle(Booking $booking, DocumentKind $kind, ?string $idempotencyKey = null): Document
    {
        $existing = Document::query()
            ->where('booking_id', $booking->id)
            ->where('kind', $kind)
            ->orderByDesc('version')
            ->first();

        $document = $existing instanceof Document
            ? $existing
            : $this->prepare->handle($booking, $kind, system: true);

        $this->send->handle($booking, $document, system: true, idempotencyKey: $idempotencyKey);

        return $document;
    }
}
