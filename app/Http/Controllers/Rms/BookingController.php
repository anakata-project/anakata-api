<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Bookings\CreateReservation;
use App\Enums\BookingSegment;
use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Exceptions\CabinUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\IndexBookingsRequest;
use App\Http\Requests\Rms\QuoteReservationRequest;
use App\Http\Requests\Rms\StoreReservationRequest;
use App\Http\Resources\Rms\BookingResource;
use App\Http\Resources\Rms\ChangeHistoryResource;
use App\Http\Resources\Rms\ReservationCreatedResource;
use App\Http\Resources\Rms\ReservationQuoteResource;
use App\Models\Booking;
use App\Models\User;
use App\Services\Pricing\ReservationQuoter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class BookingController extends Controller
{
    public function index(IndexBookingsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Booking::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $perPage = $request->integer('per_page', 50);
        $search = $request->validated('q');

        $bookings = Booking::query()
            ->select('bookings.*')
            ->join('departures', 'departures.id', '=', 'bookings.departure_id')
            ->with([
                'departure.yacht',
                'cabin',
                'contact',
                'group.coordinator',
                'owner',
                'ratesVersion',
            ])
            ->when(
                ! $actor->hasPermission(Permission::BookingsViewAll),
                fn (Builder $query) => $query->where('bookings.owner_id', $actor->id),
            )
            ->when($request->boolean('mine'), fn (Builder $query) => $query->where('bookings.owner_id', $actor->id))
            ->when(
                $request->filled('segment'),
                fn (Builder $query) => $query->ofSegment(BookingSegment::from((string) $request->validated('segment'))),
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('bookings.status', BookingStatus::from((string) $request->validated('status'))),
            )
            ->when(
                $request->filled('from'),
                fn (Builder $query) => $query->whereDate('departures.date', '>=', (string) $request->validated('from')),
            )
            ->when(
                $request->filled('to'),
                fn (Builder $query) => $query->whereDate('departures.date', '<=', (string) $request->validated('to')),
            )
            ->when(
                $request->filled('departure_id'),
                fn (Builder $query) => $query->where('bookings.departure_id', $request->validated('departure_id')),
            )
            ->when(
                $request->filled('group_id'),
                fn (Builder $query) => $query->where('bookings.group_id', $request->validated('group_id')),
            )
            ->when(is_string($search) && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $like = '%'.$search.'%';
                    $inner->where('bookings.reference', 'like', $like)
                        ->orWhere('bookings.request_reference', 'like', $like)
                        ->orWhereHas('contact', function (Builder $contact) use ($like): void {
                            $contact->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
            ->orderBy('departures.date')
            ->orderBy('bookings.reference')
            ->paginate($perPage);

        return BookingResource::collection($bookings);
    }

    public function quote(QuoteReservationRequest $request, ReservationQuoter $quoter): ReservationQuoteResource
    {
        $this->authorize('create', Booking::class);

        return new ReservationQuoteResource($quoter->quote($request->validated()));
    }

    /**
     * @throws CabinUnavailableException
     */
    public function store(StoreReservationRequest $request, CreateReservation $action): JsonResponse
    {
        $this->authorize('create', Booking::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $created = $action->handle($request->validated(), $actor);

        return (new ReservationCreatedResource($created))->response()->setStatusCode(201);
    }

    public function show(Booking $booking): BookingResource
    {
        $this->authorize('view', $booking);

        $booking->load([
            'departure.yacht',
            'cabin',
            'contact',
            'group.coordinator',
            'owner',
            'ratesVersion',
        ]);

        return new BookingResource($booking);
    }

    public function history(Booking $booking): AnonymousResourceCollection
    {
        $this->authorize('viewHistory', $booking);

        $entries = $booking->history()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return ChangeHistoryResource::collection($entries);
    }
}
