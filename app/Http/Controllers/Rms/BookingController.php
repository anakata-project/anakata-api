<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Bookings\CreateReservation;
use App\Actions\Bookings\DeleteBooking;
use App\Actions\Bookings\MoveBooking;
use App\Actions\Bookings\TransitionBooking;
use App\Actions\Bookings\UpdateBooking;
use App\Enums\BookingSegment;
use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Exceptions\CabinUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\DeleteBookingRequest;
use App\Http\Requests\Rms\IndexBookingAuditRequest;
use App\Http\Requests\Rms\IndexBookingsRequest;
use App\Http\Requests\Rms\MoveBookingRequest;
use App\Http\Requests\Rms\PreviewMoveBookingRequest;
use App\Http\Requests\Rms\QuoteReservationRequest;
use App\Http\Requests\Rms\StoreReservationRequest;
use App\Http\Requests\Rms\TransitionBookingRequest;
use App\Http\Requests\Rms\UpdateBookingRequest;
use App\Http\Resources\Rms\BookingAuditResource;
use App\Http\Resources\Rms\BookingResource;
use App\Http\Resources\Rms\ChangeHistoryResource;
use App\Http\Resources\Rms\MovePreviewResource;
use App\Http\Resources\Rms\ReservationCreatedResource;
use App\Http\Resources\Rms\ReservationQuoteResource;
use App\Models\Booking;
use App\Models\ChangeHistory;
use App\Models\User;
use App\Services\Pricing\ReservationQuoter;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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

    public function audit(IndexBookingAuditRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAudit', Booking::class);

        $perPage = $request->integer('per_page', 50);
        $from = $request->validated('from');
        $to = $request->validated('to');

        $entries = ChangeHistory::query()
            ->whereIn('event', ['booking.deleted', 'booking.released'])
            ->when(is_string($from) && $from !== '', function (Builder $query) use ($from): void {
                $query->where('created_at', '>=', $this->galapagosDayStart($from));
            })
            ->when(is_string($to) && $to !== '', function (Builder $query) use ($to): void {
                $query->where('created_at', '<=', $this->galapagosDayEnd($to));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return BookingAuditResource::collection($entries);
    }

    /**
     * @throws CabinUnavailableException
     */
    public function transition(
        TransitionBookingRequest $request,
        Booking $booking,
        TransitionBooking $action,
    ): BookingResource {
        $this->authorize('changeStatus', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new BookingResource($action->handle($booking, $request->validated(), $actor));
    }

    public function movePreview(
        PreviewMoveBookingRequest $request,
        Booking $booking,
        MoveBooking $action,
    ): MovePreviewResource {
        $this->authorize('move', $booking);

        return new MovePreviewResource($action->preview($booking, $request->validated()));
    }

    /**
     * @throws CabinUnavailableException
     */
    public function move(MoveBookingRequest $request, Booking $booking, MoveBooking $action): BookingResource
    {
        $this->authorize('move', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new BookingResource($action->handle($booking, $request->validated(), $actor));
    }

    public function update(UpdateBookingRequest $request, Booking $booking, UpdateBooking $action): BookingResource
    {
        $this->authorize('update', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new BookingResource($action->handle($booking, $request->validated(), $actor));
    }

    public function destroy(DeleteBookingRequest $request, Booking $booking, DeleteBooking $action): Response
    {
        $this->authorize('delete', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $action->handle($booking, $request->validated(), $actor);

        return response()->noContent();
    }

    private function galapagosDayStart(string $date): CarbonImmutable
    {
        return $this->galapagosDay($date)->startOfDay()->utc();
    }

    private function galapagosDayEnd(string $date): CarbonImmutable
    {
        return $this->galapagosDay($date)->endOfDay()->utc();
    }

    private function galapagosDay(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, BusinessTime::zone());

        if (! $parsed instanceof CarbonImmutable) {
            abort(422, 'Invalid date.');
        }

        return $parsed;
    }
}
