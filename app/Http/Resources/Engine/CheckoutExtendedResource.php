<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Models\CheckoutSession;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckoutSession
 */
class CheckoutExtendedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{expires_at: string, extended: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'expires_at' => Iso::utc($this->expires_at),
            'extended' => $this->extended,
        ];
    }
}
