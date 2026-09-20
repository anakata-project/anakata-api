<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Stripe webhook inbox. No audit columns — infrastructure, like ReferenceSequence.
 *
 * @property int $id
 * @property string $stripe_event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 * @property string|null $error
 * @property string|null $resolution
 */
#[Fillable([
    'stripe_event_id',
    'type',
    'payload',
    'received_at',
    'processed_at',
    'error',
    'resolution',
])]
class StripeEvent extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
