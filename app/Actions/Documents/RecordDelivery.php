<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Actions\Action;
use App\Models\Delivery;
use Illuminate\Database\UniqueConstraintViolationException;

final class RecordDelivery extends Action
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Delivery
    {
        $key = $attributes['idempotency_key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new \InvalidArgumentException('A delivery needs an idempotency key.');
        }

        $existing = Delivery::query()->where('idempotency_key', $key)->first();

        if ($existing instanceof Delivery) {
            return $existing;
        }

        try {
            $delivery = new Delivery($attributes);
            $delivery->save();

            return $delivery;
        } catch (UniqueConstraintViolationException) {
            return Delivery::query()->where('idempotency_key', $key)->firstOrFail();
        }
    }
}
