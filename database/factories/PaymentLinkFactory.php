<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentKind;
use App\Enums\PaymentLinkStatus;
use App\Models\Booking;
use App\Models\PaymentLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentLink>
 */
class PaymentLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = 'plink_test_'.fake()->unique()->numerify('###');

        return [
            'booking_id' => Booking::factory(),
            'kind' => PaymentKind::Deposit,
            'amount' => 2660,
            'stripe_id' => $id,
            'url' => 'https://buy.stripe.com/test/'.$id,
            'status' => PaymentLinkStatus::Open,
        ];
    }
}
