<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Delivery;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Delivery
 */
class DeliveryResource extends JsonResource
{
    public static $wrap = null;

    public ?string $warning = null;

    /**
     * @return array{
     *     id: int,
     *     booking_id: int,
     *     document_id: int|null,
     *     kind: string,
     *     kind_label: string,
     *     to: list<string>,
     *     cc: list<string>,
     *     subject: string,
     *     status: string,
     *     error: string|null,
     *     blocked_reason: string|null,
     *     sent_at: string|null,
     *     triggered_by: string,
     *     created_at: string,
     *     warning: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var string|null $warning */
        $warning = $this->warning;

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'document_id' => $this->document_id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'to' => $this->to,
            'cc' => $this->cc,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'error' => $this->error,
            'blocked_reason' => $this->blocked_reason,
            'sent_at' => Iso::utc($this->sent_at),
            'triggered_by' => $this->triggered_by->value,
            'created_at' => Iso::utc($this->created_at),
            // @var string|null
            'warning' => $warning,
        ];
    }
}
