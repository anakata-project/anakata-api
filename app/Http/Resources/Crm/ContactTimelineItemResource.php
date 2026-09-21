<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{at: string, kind: string, title: string, detail: string, link: array{type: string, id: int, reference: string|null}|null} $resource
 */
class ContactTimelineItemResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     at: string,
     *     kind: string,
     *     title: string,
     *     detail: string,
     *     link: array{type: string, id: int, reference: string|null}|null
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
