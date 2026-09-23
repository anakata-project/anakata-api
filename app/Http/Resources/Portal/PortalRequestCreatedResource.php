<?php

declare(strict_types=1);

namespace App\Http\Resources\Portal;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use LogicException;

class PortalRequestCreatedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{references: list<string>, status: BookingStatus, message: string}
     */
    public function toArray(Request $request): array
    {
        /** @var array{bookings: Collection<int, Booking>, message: string} $payload */
        $payload = $this->resource;
        $first = $payload['bookings']->first();

        if (! $first instanceof Booking) {
            throw new LogicException('A portal request creates at least one booking.');
        }

        $references = [];

        foreach ($payload['bookings'] as $booking) {
            $references[] = (string) $booking->displayReference();
        }

        return [
            'references' => $references,
            'status' => $this->createdStatus($first),
            'message' => $payload['message'],
        ];
    }

    private function createdStatus(Booking $booking): BookingStatus
    {
        return $booking->status;
    }
}
