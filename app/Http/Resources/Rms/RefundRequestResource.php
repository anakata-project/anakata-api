<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\Permission;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\Config\CurrentConfig;
use App\Support\BusinessHours;
use App\Support\Iso;
use App\Support\Payments\CancellationPenalty;
use App\Support\Refunds\RefundSla;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RefundRequest
 */
class RefundRequestResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['booking.contact', 'booking.departure', 'decidedBy']);

        $config = app(CurrentConfig::class);
        $rules = $config->businessRules();
        $hours = BusinessHours::fromDocument($rules);
        $sla = RefundSla::for($this->resource, $hours, $rules);
        $band = [
            'min_days' => $this->band_min_days,
            'penalty_pct' => $this->penalty_pct,
        ];
        $label = CancellationPenalty::label($band, $rules->bands);
        $actor = $request->user();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'booking' => [
                'id' => $this->booking->id,
                'reference' => $this->booking->reference,
                'display_reference' => $this->booking->displayReference(),
            ],
            'client' => $this->booking->contact->name,
            'cancelled_at' => Iso::utc($this->cancelled_at),
            'days_before_departure' => $this->days_before_departure,
            'band_min_days' => $this->band_min_days,
            'band_label' => $label,
            'penalty_pct' => $this->penalty_pct,
            'penalty_amount' => $this->penalty_amount,
            'paid_at_cancellation' => $this->paid_at_cancellation,
            'refund_due' => $this->refund_due,
            'due_by' => Iso::utc($this->due_by),
            'business_days_remaining' => $sla['business_days_remaining'],
            'sla_breached' => $sla['sla_breached'],
            'decision_reason' => $this->decision_reason,
            'decided_at' => $this->decided_at !== null ? Iso::utc($this->decided_at) : null,
            'decided_by' => $this->decidedBy === null ? null : [
                'id' => $this->decidedBy->id,
                'name' => $this->decidedBy->name,
            ],
            'executed_payment_id' => $this->executed_payment_id,
            'can_approve' => $actor instanceof User && $actor->hasPermission(Permission::RefundsApprove),
            'can_execute' => $actor instanceof User && $actor->hasPermission(Permission::RefundsExecute),
        ];
    }
}
