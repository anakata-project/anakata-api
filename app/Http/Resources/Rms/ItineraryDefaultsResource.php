<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Support\Itineraries\Defaults;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItineraryDefaultsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return Defaults::payload();
    }
}
