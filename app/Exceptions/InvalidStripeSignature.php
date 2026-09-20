<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidStripeSignature extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid Stripe webhook signature.');
    }
}
