<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Enums\CharterEnquiryStatus;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     html: string,
 *     version: int,
 *     number: string|null,
 *     state: CharterEnquiryStatus,
 *     expired: bool,
 *     price: array{lines: list<array{code: string, label: string, amount: int}>, total: int|null, deposit_pct: int|null, deposit: int|null},
 *     valid_until: string|null
 * } $resource
 */
#[SchemaName('CharterProposalViewResource')]
class CharterProposalViewResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     html: string,
     *     version: int,
     *     number: string|null,
     *     state: CharterEnquiryStatus,
     *     expired: bool,
     *     price: array{lines: list<array{code: string, label: string, amount: int}>, total: int|null, deposit_pct: int|null, deposit: int|null},
     *     valid_until: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
