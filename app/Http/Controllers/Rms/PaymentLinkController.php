<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Documents\SendPaymentRequest;
use App\Actions\Payments\CancelPaymentLink;
use App\Actions\Payments\CreatePaymentLink;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryStatus;
use App\Enums\PaymentLinkStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\CreatePaymentLinkRequest;
use App\Http\Requests\Rms\SendPaymentLinkRequest;
use App\Http\Resources\Rms\DeliveryResource;
use App\Http\Resources\Rms\PaymentLinkResource;
use App\Models\Booking;
use App\Models\Delivery;
use App\Models\PaymentLink;
use App\Models\User;
use App\Support\Documents\DeliveryKey;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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

    public function send(SendPaymentLinkRequest $request, PaymentLink $paymentLink, SendPaymentRequest $action): DeliveryResource
    {
        $this->authorize('recordPayment', $paymentLink->booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        if ($paymentLink->status !== PaymentLinkStatus::Open) {
            throw ValidationException::withMessages([
                'link' => ['Only an open payment link can be emailed.'],
            ]);
        }

        $existing = Delivery::query()
            ->where('idempotency_key', DeliveryKey::forPaymentLink($paymentLink->id))
            ->first();

        $resend = $existing instanceof Delivery
            && in_array($existing->status, [DeliveryStatus::Sent, DeliveryStatus::Failed], true);

        return new DeliveryResource($action->handle(
            $paymentLink->booking,
            DeliveryKind::PaymentLink,
            $paymentLink,
            actor: $actor,
            resend: $resend,
        ));
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
