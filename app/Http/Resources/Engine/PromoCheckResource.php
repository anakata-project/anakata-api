<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoCheckResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{valid: bool, reason: string|null, line: string|null, applies_to: list<string>}
     */
    public function toArray(Request $request): array
    {
        /** @var array{valid: bool, reason: string|null, line: string|null, applies_to: list<string>} $payload */
        $payload = $this->resource;

        return $payload;
    }
}
