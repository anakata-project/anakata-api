<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Support\Documents\DocumentPlanRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentPlanRow
 */
class DocumentPlanRowResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     booking_id: int,
     *     kind: string,
     *     name: string,
     *     recipient: string,
     *     trigger: string,
     *     date: string|null,
     *     status: string,
     *     document_id: int|null,
     *     version: int|null,
     *     delivery_id: int|null,
     *     error: string|null,
     *     payment_id: int|null,
     *     reminder_days: int|null,
     *     can_preview: bool,
     *     can_issue: bool,
     *     can_resend: bool
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var DocumentPlanRow $row */
        $row = $this->resource;

        return [
            'booking_id' => $row->bookingId,
            'kind' => $row->kind->value,
            'name' => $row->name,
            'recipient' => $row->recipient,
            'trigger' => $row->trigger,
            'date' => $row->date,
            'status' => $row->status->value,
            'document_id' => $row->documentId,
            'version' => $row->version,
            'delivery_id' => $row->deliveryId,
            'error' => $row->error,
            'payment_id' => $row->paymentId,
            'reminder_days' => $row->reminderDays,
            'can_preview' => $row->canPreview,
            'can_issue' => $row->canIssue,
            'can_resend' => $row->canResend,
        ];
    }
}
