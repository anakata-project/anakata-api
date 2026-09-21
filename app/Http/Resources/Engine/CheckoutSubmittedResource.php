<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Enums\CheckoutPath;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class CheckoutSubmittedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     path: string,
     *     references: list<string>,
     *     email: string|null,
     *     checkout_url: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array{path: CheckoutPath, bookings: Collection<int, Booking>, email: string, checkout_url?: string|null} $payload */
        $payload = $this->resource;

        $references = $payload['bookings']
            ->map(fn (Booking $booking): string => (string) $booking->displayReference())
            ->values()
            ->all();

        $body = [
            'path' => $payload['path']->value,
            'references' => $references,
        ];

        if ($payload['path'] === CheckoutPath::PayLater) {
            $body['email'] = $payload['email'];
        }

        if (isset($payload['checkout_url'])) {
            $body['checkout_url'] = $payload['checkout_url'];
        }

        return $body;
    }
}
