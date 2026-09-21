<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\RetryFailedDelivery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Crm\EventCatalogueResource;
use App\Http\Resources\Crm\FieldOwnershipResource;
use App\Http\Resources\Crm\RetrySyncFailureResource;
use App\Http\Resources\Crm\ScheduledJobResource;
use App\Http\Resources\Crm\SyncFailureResource;
use App\Http\Resources\Crm\SyncIdentityResource;
use App\Models\ContactMerge;
use App\Models\Delivery;
use App\Models\User;
use App\Support\Crm\CrmSync;
use App\Support\Crm\EventCatalogue;
use App\Support\Crm\FieldOwnership;
use App\Support\Crm\SyncFailures;
use App\Support\Crm\SyncJobs;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

final class SyncController extends Controller
{
    public function ownership(): JsonResource
    {
        $this->authorize('viewAny', CrmSync::class);

        return FieldOwnershipResource::collection(FieldOwnership::rows());
    }

    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\ScheduledJobResource>, meta: array{kpis: array{jobs_failing: int, failures_open: int, merges_this_month: int}}}',
    )]
    public function jobs(): JsonResource
    {
        $this->authorize('viewAny', CrmSync::class);

        $page = SyncJobs::list();

        return ScheduledJobResource::collection($page['jobs'])->additional([
            'meta' => [
                'kpis' => $page['kpis'],
            ],
        ]);
    }

    public function failures(): JsonResource
    {
        $this->authorize('viewAny', CrmSync::class);

        return SyncFailureResource::collection(SyncFailures::all());
    }

    public function retry(Request $request, string $id, RetryFailedDelivery $deliveries): RetrySyncFailureResource
    {
        $this->authorize('retry', CrmSync::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        if (str_starts_with($id, 'job:')) {
            $uuid = substr($id, 4);

            if ($uuid === '' || DB::table('failed_jobs')->where('uuid', $uuid)->doesntExist()) {
                abort(404);
            }

            Artisan::call('queue:retry', ['id' => [$uuid]]);

            return new RetrySyncFailureResource([
                'id' => $id,
                'retried' => true,
            ]);
        }

        if (str_starts_with($id, 'delivery:')) {
            $deliveryId = (int) substr($id, 9);
            $delivery = Delivery::query()->find($deliveryId);

            if (! $delivery instanceof Delivery) {
                abort(404);
            }

            $deliveries->handle($delivery, $actor);

            return new RetrySyncFailureResource([
                'id' => $id,
                'retried' => true,
            ]);
        }

        abort(404);
    }

    public function identity(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CrmSync::class);

        $merges = ContactMerge::query()
            ->with('mergedBy')
            ->orderByDesc('merged_at')
            ->orderByDesc('id')
            ->paginate(50);

        return SyncIdentityResource::collection($merges);
    }

    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\EventCatalogueResource>, meta: array{note: string}}',
    )]
    public function events(): JsonResource
    {
        $this->authorize('viewAny', CrmSync::class);

        return EventCatalogueResource::collection(EventCatalogue::rows())->additional([
            'meta' => [
                'note' => EventCatalogue::NOTE,
            ],
        ]);
    }
}
