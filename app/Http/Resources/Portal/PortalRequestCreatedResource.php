<?php

declare(strict_types=1);

namespace App\Http\Resources\Portal;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PortalRequestCreatedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{references: list<string>, status: string, message: string}
     */
    public function toArray(Request $request): array
    {
        /** @var array{bookings: Collection<int, Booking>, message: string} $payload */
        $payload = $this->resource;
        $first = $payload['bookings']->first();

        return [
            'references' => $payload['bookings']
                ->map(fn (Booking $booking): string => (string) $booking->displayReference())
                ->values()
                ->all(),
            'status' => $first instanceof Booking ? $first->status->value : '',
            'message' => $payload['message'],
        ];
    }
}
