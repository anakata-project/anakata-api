<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Agencies\DecideAgency;
use App\Actions\Agencies\RegisterAgency;
use App\Actions\Agencies\UpdateAgency;
use App\Enums\AgencyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\DecideAgencyRequest;
use App\Http\Requests\Rms\IndexAgenciesRequest;
use App\Http\Requests\Rms\StoreAgencyRequest;
use App\Http\Requests\Rms\UpdateAgencyRequest;
use App\Http\Resources\Rms\AgencyResource;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AgencyController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Rms\\AgencyResource>, meta: array{kpis: array{approved_agencies: int, registrations_to_review: int, agency_revenue: int, commission_accrued: int}}}',
    )]
    public function index(IndexAgenciesRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Agency::class);

        $search = $request->validated('q');

        $agencies = Agency::query()
            ->with(['users', 'decidedBy', 'bookings'])
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('status', AgencyStatus::from((string) $request->validated('status'))),
            )
            ->when(is_string($search) && $search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('contact', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('network', 'like', $like)
                        ->orWhere('reference', 'like', $like);
                });
            })
            ->orderBy('name')
            ->get();

        $approved = Agency::query()->where('status', AgencyStatus::Approved)->with('bookings')->get();
        $pending = Agency::query()->where('status', AgencyStatus::Pending)->count();
        $revenue = (int) $approved->sum(fn (Agency $agency): int => (int) $agency->bookings->sum('total'));
        $accrued = (int) $approved->sum(
            fn (Agency $agency): int => (int) $agency->bookings
                ->filter(fn (Booking $booking): bool => $booking->commission_approved)
                ->sum(fn (Booking $booking): int => $booking->commissionAmount()),
        );

        return AgencyResource::collection($agencies)->additional([
            'meta' => [
                'kpis' => [
                    'approved_agencies' => $approved->count(),
                    'registrations_to_review' => $pending,
                    'agency_revenue' => $revenue,
                    'commission_accrued' => $accrued,
                ],
            ],
        ]);
    }

    public function store(StoreAgencyRequest $request, RegisterAgency $action): JsonResponse
    {
        $this->authorize('create', Agency::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $agency = $action->handle($request->validated(), $actor);

        return (new AgencyResource($agency))->response()->setStatusCode(201);
    }

    public function show(Agency $agency): AgencyResource
    {
        $this->authorize('view', $agency);

        $resource = new AgencyResource($agency);
        $resource->detailed = true;

        return $resource;
    }

    public function update(UpdateAgencyRequest $request, Agency $agency, UpdateAgency $action): AgencyResource
    {
        $this->authorize('update', $agency);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new AgencyResource($action->handle($agency, $request->validated(), $actor));
    }

    public function decide(DecideAgencyRequest $request, Agency $agency, DecideAgency $action): AgencyResource
    {
        $this->authorize('decide', $agency);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new AgencyResource($action->handle($agency, $request->validated(), $actor));
    }
}
