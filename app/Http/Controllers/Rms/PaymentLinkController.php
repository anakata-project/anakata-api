<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Payments\CancelPaymentLink;
use App\Actions\Payments\CreatePaymentLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\CreatePaymentLinkRequest;
use App\Http\Resources\Rms\PaymentLinkResource;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Models\User;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;

final class PaymentLinkController extends Controller
{
    #[DocumentedResponse(status: 201, type: PaymentLinkResource::class)]
    public function store(CreatePaymentLinkRequest $request, Booking $booking, CreatePaymentLink $action): JsonResponse
    {
        $this->authorize('recordPayment', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $link = $action->handle($booking, $request->validated(), $actor);

        return (new PaymentLinkResource($link))->response()->setStatusCode(201);
    }

    public function cancel(PaymentLink $paymentLink, CancelPaymentLink $action): PaymentLinkResource
    {
        $this->authorize('recordPayment', $paymentLink->booking);

        $actor = request()->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new PaymentLinkResource($action->handle($paymentLink, $actor));
    }
}
