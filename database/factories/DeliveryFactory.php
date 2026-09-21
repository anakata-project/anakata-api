<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTriggeredBy;
use App\Models\Booking;
use App\Models\Delivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'document_id' => null,
            'kind' => DeliveryKind::Invoice,
            'idempotency_key' => 'invoice:'.Str::uuid()->toString(),
            'to' => ['guest@example.com'],
            'cc' => [],
            'subject' => 'Booking confirmation & invoice',
            'status' => DeliveryStatus::Queued,
            'error' => null,
            'blocked_reason' => null,
            'sent_at' => null,
            'triggered_by' => DeliveryTriggeredBy::User,
        ];
    }
}
