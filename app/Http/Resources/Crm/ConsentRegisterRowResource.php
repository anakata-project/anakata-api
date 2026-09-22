<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{purpose: string, label: string, basis: string, opt_in_needed: bool, where_captured: string, contacts: int} $resource
 */
#[SchemaName('ConsentRegisterRowResource')]
class ConsentRegisterRowResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{purpose: string, label: string, basis: string, opt_in_needed: bool, where_captured: string, contacts: int}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
