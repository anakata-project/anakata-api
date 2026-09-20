<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Payments\MarkWireReceived;
use App\Actions\Payments\RecordPayment;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\IndexPaymentsRequest;
use App\Http\Requests\Rms\MarkWireReceivedRequest;
use App\Http\Requests\Rms\RecordPaymentRequest;
use App\Http\Resources\Rms\PaymentResource;
use App\Http\Resources\Rms\RecordedPaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Support\Payments\RecordedPayment;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PaymentController extends Controller
{
    public function index(IndexPaymentsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Booking::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $perPage = $request->integer('per_page', 50);
        $search = $request->validated('q');

        $payments = Payment::query()
            ->whereHas('booking')
            ->with(['booking', 'recordedBy'])
            ->when(
                ! $actor->hasPermission(Permission::BookingsViewAll),
                fn (Builder $query) => $query->whereHas(
                    'booking',
                    fn (Builder $booking) => $booking->where('owner_id', $actor->id),
                ),
            )
            ->when(
                $request->filled('from'),
                fn (Builder $query) => $query->whereDate('paid_at', '>=', (string) $request->validated('from')),
            )
            ->when(
                $request->filled('to'),
                fn (Builder $query) => $query->whereDate('paid_at', '<=', (string) $request->validated('to')),
            )
            ->when(
                $request->filled('booking_id'),
                fn (Builder $query) => $query->where('booking_id', $request->validated('booking_id')),
            )
            ->when(
                $request->filled('kind'),
                fn (Builder $query) => $query->where('kind', PaymentKind::from((string) $request->validated('kind'))),
            )
            ->when(
                $request->filled('method'),
                fn (Builder $query) => $query->where('method', PaymentMethod::from((string) $request->validated('method'))),
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('status', PaymentStatus::from((string) $request->validated('status'))),
            )
            ->when(is_string($search) && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $like = '%'.$search.'%';
                    $inner->where('payments.reference', 'like', $like)
                        ->orWhereHas('booking', function (Builder $booking) use ($like): void {
                            $booking->where('reference', 'like', $like)
                                ->orWhere('request_reference', 'like', $like);
                        });
                });
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    public function forBooking(Booking $booking): AnonymousResourceCollection
    {
        $this->authorize('view', $booking);

        $payments = $booking->payments()
            ->with(['booking', 'recordedBy'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        return PaymentResource::collection($payments);
    }

    #[DocumentedResponse(
        status: 201,
        type: 'array{id: int, reference: string, date: string, kind: string, method: string, amount: int, status: string, gateway_id: string|null, recorded_by: string|null, can_mark_wire: bool, wire_window_ends_at: string|null, booking: App\\Http\\Resources\\Rms\\BookingResource, warnings: list<string>}',
    )]
    public function store(RecordPaymentRequest $request, Booking $booking, RecordPayment $action): JsonResponse
    {
        $this->authorize('recordPayment', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $recorded = $action->handle($booking, $request->validated(), $actor);

        $recorded = new RecordedPayment(
            $recorded->payment->load(['booking', 'recordedBy']),
            Booking::query()->withLedgerAggregates()->with([
                'departure.yacht',
                'departure.itinerary',
                'cabin',
                'contact',
                'group.coordinator',
                'owner',
                'ratesVersion',
                'bookingRequest',
                'activeClaims',
            ])->findOrFail($recorded->booking->getKey()),
            $recorded->warnings,
        );

        return (new RecordedPaymentResource($recorded))->response()->setStatusCode(201);
    }

    public function markReceived(MarkWireReceivedRequest $request, Payment $payment, MarkWireReceived $action): PaymentResource
    {
        $this->authorize('markReceived', $payment);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new PaymentResource($action->handle($payment, $request->validated(), $actor));
    }
}
