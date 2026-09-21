<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Documents\PrepareIssueDocument;
use App\Actions\Documents\SendDocument;
use App\Enums\DeliveryStatus;
use App\Enums\DocumentKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\SendWireInstructionsRequest;
use App\Http\Resources\Rms\DeliveryResource;
use App\Models\Booking;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\User;
use App\Support\Documents\DeliveryKey;
use App\Support\Documents\WireWarning;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DeliveryController extends Controller
{
    public function index(Booking $booking): AnonymousResourceCollection
    {
        $this->authorize('view', $booking);

        $deliveries = $booking->deliveries()
            ->orderByDesc('id')
            ->get();

        return DeliveryResource::collection($deliveries);
    }

    #[DocumentedResponse(status: 201, type: DeliveryResource::class)]
    public function sendWire(
        SendWireInstructionsRequest $request,
        Booking $booking,
        PrepareIssueDocument $issue,
        SendDocument $send,
    ): DeliveryResource {
        $this->authorize('recordPayment', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $document = $booking->documents()
            ->where('kind', DocumentKind::WireInstructions)
            ->orderByDesc('version')
            ->first();

        if (! $document instanceof Document) {
            $document = $issue->handle($booking, DocumentKind::WireInstructions, actor: $actor);
        }

        $existing = Delivery::query()
            ->where('idempotency_key', DeliveryKey::forDocument($document))
            ->first();

        $resend = $existing instanceof Delivery
            && in_array($existing->status, [DeliveryStatus::Sent, DeliveryStatus::Failed], true);

        $delivery = $send->handle($booking, $document, $actor, resend: $resend);

        $placeholders = ($document->snapshot['placeholders'] ?? false) === true;

        $resource = new DeliveryResource($delivery);
        $resource->warning = $placeholders ? WireWarning::MESSAGE : null;

        return $resource;
    }
}
