<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Payments\ApplyUnmatchedGatewayPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\ApplyReconciliationRequest;
use App\Http\Requests\Rms\ReconciliationRequest;
use App\Http\Resources\Rms\PaymentResource;
use App\Http\Resources\Rms\ReconciliationResource;
use App\Models\Booking;
use App\Models\User;
use App\Support\Payments\ReconciliationReport;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;

final class ReconciliationController extends Controller
{
    public function index(ReconciliationRequest $request, ReconciliationReport $report): ReconciliationResource
    {
        $this->authorize('viewAudit', Booking::class);

        return new ReconciliationResource($report->forWindow(
            (string) $request->validated('from'),
            (string) $request->validated('to'),
        ));
    }

    #[DocumentedResponse(status: 201, type: PaymentResource::class)]
    public function apply(ApplyReconciliationRequest $request, ApplyUnmatchedGatewayPayment $action): PaymentResource
    {
        $booking = Booking::query()->findOrFail($request->integer('booking_id'));

        $this->authorize('recordPayment', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $payment = $action->handle($booking, $request->validated(), $actor);

        return new PaymentResource($payment->load(['booking', 'recordedBy']));
    }
}
