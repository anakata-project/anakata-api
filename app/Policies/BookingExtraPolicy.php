<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BookingExtra;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class BookingExtraPolicy extends Policy
{
    public function delete(User $actor, BookingExtra $extra): Response
    {
        $extra->loadMissing('booking');

        return app(BookingPolicy::class)->updateExtras($actor, $extra->booking);
    }
}
