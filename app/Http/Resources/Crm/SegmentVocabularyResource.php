<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     data: array{
 *         fields: list<array<string, mixed>>,
 *         combinators: list<string>
 *     }
 * } $resource
 */
#[SchemaName('CrmSegmentVocabularyResource')]
class SegmentVocabularyResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     data: array{
     *         fields: list<array<string, mixed>>,
     *         combinators: list<string>
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
