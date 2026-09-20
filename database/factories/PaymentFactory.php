<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\BusinessTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kind = PaymentKind::Deposit;

        return [
            'booking_id' => Booking::factory(),
            'kind' => $kind,
            'method' => PaymentMethod::CardStripe,
            'amount' => 2660,
            'reference' => 'ANK-2026-'.str_pad((string) fake()->unique()->numberBetween(100, 999), 4, '0', STR_PAD_LEFT).'-'.$kind->letter().'01',
            'gateway_id' => null,
            'status' => PaymentStatus::Settled,
            'paid_at' => BusinessTime::now()->toDateString(),
            'recorded_by' => null,
            'note' => null,
        ];
    }
}
