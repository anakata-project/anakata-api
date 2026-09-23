<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{accepted: bool} $resource
 */
#[SchemaName('MarketingLeadResource')]
class MarketingLeadResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{accepted: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'accepted' => true,
        ];
    }
}
