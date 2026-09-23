<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\UpdateJourney;
use App\Enums\JourneyEnrolmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\UpdateJourneyRequest;
use App\Http\Resources\Crm\CrmJourneyEnrolmentResource;
use App\Http\Resources\Crm\CrmJourneyResource;
use App\Models\Contact;
use App\Models\Journey;
use App\Models\JourneyEnrolment;
use App\Models\User;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class JourneyController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\CrmJourneyResource>}',
    )]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Journey::class);

        $journeys = Journey::query()->inDisplayOrder()->with('steps')->get();
        $this->attachCounts($journeys);

        return CrmJourneyResource::collection($journeys);
    }

    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\CrmJourneyEnrolmentResource>, links: array{first: string|null, last: string|null, prev: string|null, next: string|null}, meta: array{current_page: int, from: int|null, last_page: int, path: string|null, per_page: int, to: int|null, total: int}}',
    )]
    public function enrolments(Journey $journey): AnonymousResourceCollection
    {
        $this->authorize('view', $journey);

        $rows = $journey->enrolments()
            ->with(['contact', 'booking', 'journey.steps', 'sends'])
            ->orderByDesc('enrolled_at')
            ->paginate(25);

        return CrmJourneyEnrolmentResource::collection($rows);
    }

    #[DocumentedResponse(status: 200, type: 'App\\Http\\Resources\\Crm\\CrmJourneyResource')]
    public function update(UpdateJourneyRequest $request, Journey $journey, UpdateJourney $update): CrmJourneyResource
    {
        $this->authorize('update', $journey);

        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new HttpException(403, 'You cannot change a journey.');
        }

        $saved = $update->handle($journey, $request->boolean('active'), $actor);
        $saved->load('steps');
        $this->attachCounts(collect([$saved]));

        return new CrmJourneyResource($saved);
    }

    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\CrmJourneyEnrolmentResource>}',
    )]
    public function forContact(Contact $contact): AnonymousResourceCollection
    {
        $this->authorize('view', $contact);

        $rows = JourneyEnrolment::query()
            ->where('contact_id', $contact->id)
            ->with(['contact', 'booking', 'journey.steps', 'sends'])
            ->orderByDesc('enrolled_at')
            ->get();

        return CrmJourneyEnrolmentResource::collection($rows);
    }

    /**
     * @param  iterable<Journey>  $journeys
     */
    private function attachCounts(iterable $journeys): void
    {
        $counts = DB::table('journey_enrolments')
            ->where('status', JourneyEnrolmentStatus::Active->value)
            ->selectRaw('journey_id, position, COUNT(*) as total')
            ->groupBy('journey_id', 'position')
            ->get();

        $lookup = [];

        foreach ($counts as $row) {
            $lookup[$row->journey_id.'-'.$row->position] = (int) $row->total;
        }

        foreach ($journeys as $journey) {
            foreach ($journey->steps as $step) {
                $step->setAttribute('active_count', $lookup[$journey->id.'-'.$step->position] ?? 0);
            }
        }
    }
}
