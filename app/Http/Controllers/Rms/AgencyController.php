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
use App\Models\User;
use App\Services\Config\CurrentConfig;
use App\Support\Agencies\AgencyBookingWindow;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AgencyController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Rms\\AgencyResource>, meta: array{kpis: array{approved_agencies: int, registrations_to_review: int, agency_revenue: int, commission_accrued: int, agency_approval_business_days: int, commission_payable_days: int, commission_cap_pct: int, commission_default_pct: int}}}',
    )]
    public function index(IndexAgenciesRequest $request, CurrentConfig $config): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Agency::class);

        $search = $request->validated('q');
        $from = self::dateQuery($request->validated('from'));
        $to = self::dateQuery($request->validated('to'));

        $agencies = Agency::query()
            ->with(['users', 'decidedBy', 'bookings.departure'])
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
            ->get()
            ->filter(fn (Agency $agency): bool => AgencyBookingWindow::visible($agency, $from, $to))
            ->values();

        $rules = $config->businessRules();
        $approved = Agency::query()->where('status', AgencyStatus::Approved)->with('bookings.departure')->get();
        $totals = AgencyBookingWindow::stats(
            $approved->flatMap(
                fn (Agency $agency) => AgencyBookingWindow::inRange($agency->bookings, $from, $to),
            ),
        );

        return AgencyResource::collection($agencies)->additional([
            'meta' => [
                'kpis' => [
                    'approved_agencies' => $approved->count(),
                    'registrations_to_review' => Agency::query()->where('status', AgencyStatus::Pending)->count(),
                    'agency_revenue' => $totals['revenue'],
                    'commission_accrued' => $totals['commission_accrued'],
                    'agency_approval_business_days' => $rules->sla->agencyApprovalBusinessDays,
                    'commission_payable_days' => $rules->commission->payableDaysAfterCruise,
                    'commission_cap_pct' => $rules->commission->capPct,
                    'commission_default_pct' => $rules->commission->defaultPct,
                ],
            ],
        ]);
    }

    private static function dateQuery(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
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
