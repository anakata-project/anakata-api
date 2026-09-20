<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Support\Payments\RecordedPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecordedPayment
 */
class RecordedPaymentResource extends JsonResource
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
     *     wire_window_ends_at: string|null,
     *     booking: array<string, mixed>,
     *     warnings: list<string>
     * }
     */
    public function toArray(Request $request): array
    {
        $payment = (new PaymentResource($this->payment))->toArray($request);

        return [
            ...$payment,
            'booking' => (new BookingResource($this->booking))->toArray($request),
            'warnings' => $this->warnings,
        ];
    }
}
