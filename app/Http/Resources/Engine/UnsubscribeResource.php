<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{valid: bool, already_unsubscribed: bool} $resource
 */
#[SchemaName('UnsubscribeResource')]
class UnsubscribeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{valid: bool, already_unsubscribed: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'valid' => true,
            'already_unsubscribed' => $this->resource['already_unsubscribed'],
        ];
    }
}
