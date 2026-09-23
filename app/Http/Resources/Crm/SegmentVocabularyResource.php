<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     data: array{
 *         fields: list<array{
 *             field: string,
 *             label: string,
 *             operators: list<string>,
 *             value: string,
 *             values?: list<string>,
 *             params?: list<array{name: string, type: string, values?: list<string>}>
 *         }>,
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
     *         fields: list<array{
     *             field: string,
     *             label: string,
     *             operators: list<string>,
     *             value: string,
     *             values?: list<string>,
     *             params?: list<array{name: string, type: string, values?: list<string>}>
     *         }>,
     *         combinators: list<string>
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
