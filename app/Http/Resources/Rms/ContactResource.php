<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string|null,
     *     phone: string|null,
     *     country: string|null,
     *     preferred_channel: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'preferred_channel' => $this->preferred_channel->value,
        ];
    }
}
