<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Bookings\UpdateBookingBilling;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\UpdateBookingBillingRequest;
use App\Http\Resources\Rms\BookingResource;
use App\Models\Booking;
use App\Models\User;

final class BookingBillingController extends Controller
{
    public function update(UpdateBookingBillingRequest $request, Booking $booking, UpdateBookingBilling $action): BookingResource
    {
        $this->authorize('updateBilling', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new BookingResource($action->handle($booking, $request->validated(), $actor));
    }
}
