<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\PaymentLink;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentLink
 */
class PaymentLinkResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     kind: string,
     *     amount: int,
     *     stripe_id: string,
     *     url: string,
     *     status: string,
     *     mode: string,
     *     created_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'amount' => $this->amount,
            'stripe_id' => $this->stripe_id,
            'url' => $this->url,
            'status' => $this->status->value,
            'mode' => (string) config('services.stripe.mode'),
            'created_at' => Iso::utc($this->created_at),
        ];
    }
}
