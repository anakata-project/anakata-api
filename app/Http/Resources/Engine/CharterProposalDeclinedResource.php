<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Enums\CharterEnquiryStatus;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{status: CharterEnquiryStatus} $resource
 */
#[SchemaName('CharterProposalDeclinedResource')]
class CharterProposalDeclinedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{status: CharterEnquiryStatus}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
