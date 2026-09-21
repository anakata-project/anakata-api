<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Offers\ApproveOffer;
use App\Actions\Offers\CreateOffer;
use App\Actions\Offers\PauseOffer;
use App\Actions\Offers\RejectOffer;
use App\Actions\Offers\ResumeOffer;
use App\Actions\Offers\UpdateOffer;
use App\Enums\OfferChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\ApproveOfferRequest;
use App\Http\Requests\Rms\IndexOffersRequest;
use App\Http\Requests\Rms\RejectOfferRequest;
use App\Http\Requests\Rms\StoreOfferRequest;
use App\Http\Requests\Rms\UpdateOfferRequest;
use App\Http\Resources\Rms\OfferResource;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OfferController extends Controller
{
    public function index(IndexOffersRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Offer::class);

        $search = $request->validated('q');
        $channel = $request->validated('channel');
        $status = $request->validated('status');
        $from = self::dateQuery($request->validated('from'));
        $to = self::dateQuery($request->validated('to'));

        $offers = Offer::query()
            ->with('approvedBy')
            ->when(
                is_string($channel) && $channel !== '',
                fn (Builder $query) => $query->where('channel', OfferChannel::from((string) $channel)),
            )
            ->when(is_string($search) && $search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like);
                });
            })
            ->orderBy('code')
            ->get()
            ->filter(function (Offer $offer) use ($status, $from, $to): bool {
                if (is_string($status) && $status !== '' && $offer->derivedStatus() !== $status) {
                    return false;
                }

                return $offer->spanOverlaps($from, $to);
            })
            ->values();

        return OfferResource::collection($offers);
    }

    public function store(StoreOfferRequest $request, CreateOffer $action): JsonResponse
    {
        $this->authorize('create', Offer::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $offer = $action->handle($request->validated(), $actor);

        return (new OfferResource($offer))->response()->setStatusCode(201);
    }

    public function show(Offer $offer): OfferResource
    {
        $this->authorize('view', $offer);

        return new OfferResource($offer);
    }

    public function update(UpdateOfferRequest $request, Offer $offer, UpdateOffer $action): OfferResource
    {
        $this->authorize('update', $offer);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new OfferResource($action->handle($offer, $request->validated(), $actor));
    }

    public function approve(ApproveOfferRequest $request, Offer $offer, ApproveOffer $action): OfferResource
    {
        $this->authorize('approve', $offer);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new OfferResource($action->handle($offer, $request->validated(), $actor));
    }

    public function reject(RejectOfferRequest $request, Offer $offer, RejectOffer $action): OfferResource
    {
        $this->authorize('reject', $offer);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new OfferResource($action->handle($offer, $request->validated(), $actor));
    }

    public function pause(Request $request, Offer $offer, PauseOffer $action): OfferResource
    {
        $this->authorize('pause', $offer);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new OfferResource($action->handle($offer, $actor));
    }

    public function resume(Request $request, Offer $offer, ResumeOffer $action): OfferResource
    {
        $this->authorize('resume', $offer);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new OfferResource($action->handle($offer, $actor));
    }

    private static function dateQuery(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
