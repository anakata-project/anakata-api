<?php

declare(strict_types=1);

namespace Tests\Support\CalendarDate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CalendarDateHost
 */
class CalendarDateHostResource extends JsonResource
{
    /**
     * @return array{day: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'day' => $this->day->toDateString(),
        ];
    }
}
