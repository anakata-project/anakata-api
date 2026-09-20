<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CabinClaim;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

final class HoldExpired
{
    use Dispatchable;

    public function __construct(
        public Model $holder,
        public CabinClaim $claim,
    ) {}
}
