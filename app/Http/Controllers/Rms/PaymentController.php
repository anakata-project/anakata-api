<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\IndexPaymentsRequest;
use App\Http\Resources\Rms\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
}
