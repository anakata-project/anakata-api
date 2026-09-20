<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     reference: string,
     *     date: string,
     *     kind: string,
     *     method: string,
     *     amount: int,
     *     status: string,
     *     gateway_id: string|null,
     *     recorded_by: string|null,
     *     can_mark_wire: bool,
     *     booking: array{id: int, display_reference: string|null}
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['booking', 'recordedBy']);

        $actor = $request->user();
        $recordedBy = $this->recordedBy;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'date' => $this->paid_at->toDateString(),
            'kind' => $this->kind->value,
            'method' => $this->method->value,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'gateway_id' => $this->gateway_id,
            'recorded_by' => $recordedBy instanceof User ? $recordedBy->name : null,
            'can_mark_wire' => $actor instanceof User
                && $actor->hasPermission(Permission::PaymentsMarkWireReceived)
                && $this->status === PaymentStatus::AwaitingWire,
            'booking' => [
                'id' => $this->booking->id,
                'display_reference' => $this->booking->displayReference(),
            ],
        ];
    }
}
