<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceCheckResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     scenarios: list<array{
     *         key: string,
     *         label: string,
     *         published: array<string, mixed>,
     *         draft: array<string, mixed>,
     *         difference: int|null
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var list<array{key: string, label: string, published: array<string, mixed>, draft: array<string, mixed>, difference: int|null}> $scenarios */
        $scenarios = $this->resource;

        return [
            'scenarios' => $scenarios,
        ];
    }
}
