<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Reports\UpdateReportSubscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\UpdateReportSubscriptionRequest;
use App\Http\Resources\Rms\ReportRunResource;
use App\Http\Resources\Rms\ReportSubscriptionResource;
use App\Models\ReportSubscription;
use App\Models\User;
use App\Support\Reports\ReportDispatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReportSubscriptionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ReportSubscriptionResource::collection(
            ReportSubscription::query()->orderBy('id')->get(),
        );
    }

    public function update(
        UpdateReportSubscriptionRequest $request,
        ReportSubscription $subscription,
        UpdateReportSubscription $update,
    ): ReportSubscriptionResource {
        $actor = $this->actor();

        /** @var array{active?: bool, send_at?: string, weekday?: int|null, day_of_month?: int|null} $changes */
        $changes = $request->safe()->only(['active', 'send_at', 'weekday', 'day_of_month']);

        return new ReportSubscriptionResource($update->handle($subscription, $changes, $actor));
    }

    public function runNow(ReportSubscription $subscription, ReportDispatch $dispatch): JsonResponse
    {
        $run = $dispatch->runNow($subscription, $this->actor());

        return (new ReportRunResource($run))->response()->setStatusCode(201);
    }

    private function actor(): User
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return $actor;
    }
}
