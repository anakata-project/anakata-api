<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\CreateSegment;
use App\Actions\Crm\UpdateSegment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\IndexSegmentContactsRequest;
use App\Http\Requests\Crm\StoreSegmentRequest;
use App\Http\Requests\Crm\UpdateSegmentRequest;
use App\Http\Resources\Crm\ContactResource;
use App\Http\Resources\Crm\SegmentResource;
use App\Http\Resources\Crm\SegmentVocabularyResource;
use App\Models\Contact;
use App\Models\Segment;
use App\Models\User;
use App\Support\Crm\SegmentQuery;
use App\Support\Crm\SegmentVocabulary;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SegmentController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\SegmentResource>, meta: array{cap: int, capped: bool, message: string|null}}',
    )]
    public function index(SegmentQuery $segments): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Segment::class);

        $total = Segment::query()->count();
        $rows = Segment::query()->inDisplayOrder()->limit(SegmentQuery::CAP)->get();

        $rows->each(function (Segment $segment) use ($segments): void {
            $segment->setAttribute('live_count', $segments->count($segment));
        });

        $capped = $total > SegmentQuery::CAP;

        return SegmentResource::collection($rows)->additional([
            'meta' => [
                'cap' => SegmentQuery::CAP,
                'capped' => $capped,
                'message' => $capped
                    ? 'The segment list is capped at '.SegmentQuery::CAP.' definitions.'
                    : null,
            ],
        ]);
    }

    #[DocumentedResponse(
        status: 200,
        type: 'array{data: array{fields: list<array<string, mixed>>, combinators: list<string>}}',
    )]
    public function vocabulary(): SegmentVocabularyResource
    {
        $this->authorize('viewAny', Segment::class);

        return new SegmentVocabularyResource([
            'data' => SegmentVocabulary::describe(),
        ]);
    }

    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\ContactResource>, links: array{first: string|null, last: string|null, prev: string|null, next: string|null}, meta: array{current_page: int, from: int|null, last_page: int, path: string|null, per_page: int, to: int|null, total: int}}',
    )]
    public function contacts(IndexSegmentContactsRequest $request, Segment $segment, SegmentQuery $segments): AnonymousResourceCollection
    {
        $this->authorize('view', $segment);

        $page = $segments->apply(Contact::query(), $segment)
            ->withDerived()
            ->orderBy('contacts.id')
            ->paginate($request->integer('per_page', 50));

        return ContactResource::collection($page);
    }

    #[DocumentedResponse(status: 201, type: 'App\\Http\\Resources\\Crm\\SegmentResource')]
    public function store(StoreSegmentRequest $request, CreateSegment $create, SegmentQuery $segments): JsonResponse
    {
        $this->authorize('create', Segment::class);
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new HttpException(403, 'You cannot manage segments.');
        }

        /** @var array{name: string, sentence: string, conditions: array<string, mixed>, dimensions: list<array{axis: string, label: string}>, kind: string, feeds: string, active?: bool} $data */
        $data = $request->validated();
        $segment = $create->handle($data, $actor);
        $segment->setAttribute('live_count', $segments->count($segment));

        return (new SegmentResource($segment))->response()->setStatusCode(201);
    }

    #[DocumentedResponse(status: 200, type: 'App\\Http\\Resources\\Crm\\SegmentResource')]
    public function update(UpdateSegmentRequest $request, Segment $segment, UpdateSegment $update, SegmentQuery $segments): SegmentResource
    {
        $this->authorize('update', $segment);
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new HttpException(403, 'You cannot manage segments.');
        }

        /** @var array{name?: string, sentence?: string, conditions?: array<string, mixed>, dimensions?: list<array{axis: string, label: string}>, kind?: string, feeds?: string, active?: bool} $data */
        $data = $request->validated();
        $saved = $update->handle($segment, $data, $actor);
        $saved->setAttribute('live_count', $segments->count($saved));

        return new SegmentResource($saved);
    }
}
