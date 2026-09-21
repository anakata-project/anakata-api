<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DocumentPlanKind;
use App\Enums\DocumentPlanStatus;

final readonly class DocumentPlanRow
{
    public function __construct(
        public int $bookingId,
        public DocumentPlanKind $kind,
        public string $name,
        public string $recipient,
        public string $trigger,
        public ?string $date,
        public DocumentPlanStatus $status,
        public ?int $documentId,
        public ?int $version,
        public ?int $deliveryId,
        public ?string $error,
        public bool $canPreview,
        public bool $canIssue,
        public bool $canResend,
        public ?int $reminderDays = null,
        public ?int $paymentId = null,
    ) {}
}
