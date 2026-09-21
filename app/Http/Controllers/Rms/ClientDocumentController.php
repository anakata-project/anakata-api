<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Enums\DocumentPlanKind;
use App\Enums\DocumentPlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\IndexClientDocumentsRequest;
use App\Http\Resources\Rms\DocumentPlanRowResource;
use App\Models\Booking;
use App\Models\User;
use App\Support\Documents\DocumentPlan;
use App\Support\Documents\DocumentPlanRow;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

final class ClientDocumentController extends Controller
{
    public function index(IndexClientDocumentsRequest $request, DocumentPlan $plan): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Booking::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $perPage = $request->integer('per_page', 50);
        $page = max(1, $request->integer('page', 1));
        $kind = $request->filled('kind')
            ? DocumentPlanKind::from((string) $request->validated('kind'))
            : null;
        $status = $request->filled('status')
            ? DocumentPlanStatus::from((string) $request->validated('status'))
            : null;
        $search = $request->validated('q');

        $bookings = Booking::query()
            ->select('bookings.*')
            ->join('departures', 'departures.id', '=', 'bookings.departure_id')
            ->with([
                'departure',
                'contact',
                'group.coordinator',
                'agency',
                'guests',
                'extras',
                'payments',
                'documents.deliveries',
                'deliveries',
            ])
            ->visibleTo($actor)
            ->departingBetween(
                is_string($request->validated('from')) ? $request->validated('from') : null,
                is_string($request->validated('to')) ? $request->validated('to') : null,
            )
            ->when(
                is_string($search) && $search !== '',
                function ($query) use ($search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('bookings.reference', 'like', '%'.$search.'%')
                            ->orWhere('bookings.request_reference', 'like', '%'.$search.'%')
                            ->orWhereHas('contact', fn ($contact) => $contact->where('name', 'like', '%'.$search.'%'));
                    });
                },
            )
            ->orderBy('departures.date')
            ->orderBy('bookings.id')
            ->get();

        $rows = $bookings
            ->flatMap(fn (Booking $booking): array => $plan->for($booking, $actor))
            ->when(
                $kind instanceof DocumentPlanKind,
                fn ($collection) => $collection->filter(
                    fn (DocumentPlanRow $row): bool => $row->kind === $kind,
                ),
            )
            ->when(
                $status instanceof DocumentPlanStatus,
                fn ($collection) => $collection->filter(
                    fn (DocumentPlanRow $row): bool => $row->status === $status,
                ),
            )
            ->values();

        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return DocumentPlanRowResource::collection($paginator);
    }
}
