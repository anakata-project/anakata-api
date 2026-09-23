<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Resources\Portal\PortalBookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PortalBookingController extends PortalController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $agency = $this->agency($request);
        $perPage = $request->integer('per_page', 50);

        $bookings = Booking::query()
            ->where('agency_id', $agency->id)
            ->with(['departure.itinerary', 'contact', 'guests'])
            ->withChargesSummary()
            ->withLedgerAggregates()
            ->orderByDesc('id')
            ->paginate($perPage);

        return PortalBookingResource::collection($bookings);
    }
}
