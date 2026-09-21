<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     kinds: list<array{value: string, label: string, recordable: bool}>,
 *     methods: list<array{value: string, label: string, recordable: bool}>
 * } $resource
 */
class PaymentOptionsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     kinds: list<array{value: string, label: string, recordable: bool}>,
     *     methods: list<array{value: string, label: string, recordable: bool}>
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
