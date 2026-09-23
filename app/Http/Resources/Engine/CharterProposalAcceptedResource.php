<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{booking_reference: string, deposit_due_on: string|null} $resource
 */
#[SchemaName('CharterProposalAcceptedResource')]
class CharterProposalAcceptedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{booking_reference: string, deposit_due_on: string|null}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
