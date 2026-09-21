<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\CharterEnquiry;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CharterEnquiry
 */
class CharterEnquiryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     preferred_from: string|null,
     *     preferred_to: string|null,
     *     departure: array{id: int, date: string}|null,
     *     guests: int,
     *     contact: array{name: string, email: string|null, phone: string|null},
     *     message: string,
     *     source: string,
     *     status: string,
     *     created_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['contact', 'departure']);

        return [
            'id' => $this->id,
            'preferred_from' => $this->preferred_from?->toDateString(),
            'preferred_to' => $this->preferred_to?->toDateString(),
            'departure' => $this->departure === null ? null : [
                'id' => $this->departure->id,
                'date' => $this->departure->date->toDateString(),
            ],
            'guests' => $this->guests,
            'contact' => [
                'name' => $this->contact->name,
                'email' => $this->contact->email,
                'phone' => $this->contact->phone,
            ],
            'message' => $this->message,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'created_at' => Iso::utc($this->created_at),
        ];
    }
}
