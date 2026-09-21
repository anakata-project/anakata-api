<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\BookingExtra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingExtra
 */
class BookingExtraResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     booking_id: int,
     *     code: string,
     *     name: string,
     *     unit: string,
     *     qty: int,
     *     rate_usd: int,
     *     amount: int,
     *     note: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'code' => $this->code,
            'name' => $this->name,
            'unit' => $this->unit,
            'qty' => $this->qty,
            'rate_usd' => $this->rate_usd,
            'amount' => $this->amount(),
            'note' => $this->note,
        ];
    }
}
