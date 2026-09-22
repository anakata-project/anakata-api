<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\AssignDeal;
use App\Actions\Crm\BindDeal;
use App\Actions\Crm\CreateUnboundDeal;
use App\Actions\Crm\MoveDealStage;
use App\Enums\DealStage;
use App\Enums\DealType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\AssignDealRequest;
use App\Http\Requests\Crm\BindDealRequest;
use App\Http\Requests\Crm\MoveDealStageRequest;
use App\Http\Requests\Crm\StoreDealRequest;
use App\Http\Resources\Crm\DealResource;
use App\Http\Resources\Crm\PipelineResource;
use App\Http\Resources\Crm\StageMapResource;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use App\Support\Crm\DealDrawer;
use App\Support\Crm\DealStageMap;
use App\Support\Crm\PipelineBoard;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Request;

final class DealController extends Controller
{
    #[DocumentedResponse(status: 200, type: 'array{data: App\\Http\\Resources\\Crm\\PipelineResource}')]
    public function pipeline(Request $request): PipelineResource
    {
        $this->authorize('viewAny', Deal::class);
        /** @var User $actor */
        $actor = $request->user();

        $owner = $request->query('owner');
        $type = $request->query('type');
        $q = $request->query('q');

        return new PipelineResource(PipelineBoard::build($actor, [
            'owner' => is_string($owner) ? $owner : null,
            'type' => is_string($type) ? $type : null,
            'q' => is_string($q) ? $q : null,
        ]));
    }

    public function stageMap(): StageMapResource
    {
        $this->authorize('viewAny', Deal::class);

        return new StageMapResource(['data' => DealStageMap::rows()]);
    }

    public function show(Deal $deal): DealResource
    {
        $this->authorize('view', $deal);

        return new DealResource(DealDrawer::for($deal));
    }

    public function store(StoreDealRequest $request, CreateUnboundDeal $action): DealResource
    {
        $this->authorize('create', Deal::class);
        /** @var User $actor */
        $actor = $request->user();
        $contact = Contact::query()->findOrFail($request->integer('contact_id'));

        $deal = $action->handle(
            $contact,
            $actor,
            $request->string('title')->toString(),
            DealType::from($request->string('type')->toString()),
            DealStage::from($request->string('stage')->toString()),
            $request->filled('estimate') ? $request->integer('estimate') : null,
            $request->filled('notes') ? $request->string('notes')->toString() : null,
        );

        return new DealResource(DealDrawer::for($deal));
    }

    public function assign(AssignDealRequest $request, Deal $deal, AssignDeal $action): DealResource
    {
        $this->authorize('update', $deal);
        /** @var User $actor */
        $actor = $request->user();

        $deal = $action->handle(
            $deal,
            $actor,
            $request->filled('user_id') ? $request->integer('user_id') : null,
        );

        return new DealResource(DealDrawer::for($deal));
    }

    public function bind(BindDealRequest $request, Deal $deal, BindDeal $action): DealResource
    {
        $this->authorize('update', $deal);

        $deal = $action->handle(
            $deal,
            $request->filled('booking_id') ? $request->integer('booking_id') : null,
            $request->filled('group_id') ? $request->integer('group_id') : null,
        );

        return new DealResource(DealDrawer::for($deal));
    }

    public function stage(MoveDealStageRequest $request, Deal $deal, MoveDealStage $action): DealResource
    {
        $this->authorize('update', $deal);
        /** @var User $actor */
        $actor = $request->user();

        $deal = $action->handle(
            $deal,
            $actor,
            DealStage::from($request->string('stage')->toString()),
            $request->filled('reason') ? $request->string('reason')->toString() : null,
        );

        return new DealResource(DealDrawer::for($deal));
    }
}
