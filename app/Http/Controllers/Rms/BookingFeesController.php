<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Extras\UpdateBookingFees;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\UpdateBookingFeesRequest;
use App\Http\Resources\Rms\BookingResource;
use App\Models\Booking;
use App\Models\User;

final class BookingFeesController extends Controller
{
    public function update(UpdateBookingFeesRequest $request, Booking $booking, UpdateBookingFees $action): BookingResource
    {
        $this->authorize('updateFees', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new BookingResource($action->handle($booking, $request->validated(), $actor));
    }
}
