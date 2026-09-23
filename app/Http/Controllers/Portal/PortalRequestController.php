<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\SubmitPortalRequest;
use App\Http\Requests\Portal\StorePortalRequestRequest;
use App\Http\Resources\Portal\PortalRequestCreatedResource;
use App\Http\Resources\Portal\PortalRequestResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PortalRequestController extends PortalController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $agency = $this->agency($request);
        $perPage = $request->integer('per_page', 50);

        $bookings = Booking::query()
            ->where('agency_id', $agency->id)
            ->whereHas('bookingRequest')
            ->with(['contact', 'guests', 'bookingRequest'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return PortalRequestResource::collection($bookings);
    }

    public function store(StorePortalRequestRequest $request, SubmitPortalRequest $action): JsonResponse
    {
        $created = $action->handle($this->agencyUser($request), $request->validated());

        return (new PortalRequestCreatedResource($created))->response()->setStatusCode(201);
    }
}
