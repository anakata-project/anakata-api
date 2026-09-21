<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Models\Booking;
use App\Models\CheckoutSession;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckoutSession
 */
class CheckoutStatusResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     status: string,
     *     path: string|null,
     *     email: string|null,
     *     bookings: list<array{reference: string|null, status: string}>,
     *     stripe_checkout_session_id: string|null,
     *     stripe_expires_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['bookings.contact']);

        $email = $this->bookings
            ->map(fn (Booking $booking): ?string => $booking->contact->email)
            ->first(fn (?string $value): bool => is_string($value) && $value !== '');

        return [
            'status' => $this->status->value,
            'path' => $this->path?->value,
            'email' => is_string($email) ? $email : null,
            'bookings' => $this->bookings
                ->map(fn (Booking $booking): array => [
                    'reference' => $booking->displayReference(),
                    'status' => $booking->status->value,
                ])
                ->values()
                ->all(),
            'stripe_checkout_session_id' => $this->stripe_checkout_session_id,
            'stripe_expires_at' => Iso::utc($this->stripe_expires_at),
        ];
    }
}
