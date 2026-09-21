<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Documents\PrepareIssueDocument;
use App\Actions\Documents\SendDocument;
use App\Enums\DeliveryStatus;
use App\Enums\DocumentKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\IssueDocumentRequest;
use App\Http\Requests\Rms\SendDocumentRequest;
use App\Http\Resources\Rms\DeliveryResource;
use App\Http\Resources\Rms\DocumentResource;
use App\Models\Booking;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\Payment;
use App\Models\User;
use App\Services\Documents\DocumentHtml;
use App\Support\Documents\DeliveryKey;
use App\Support\Documents\Snapshots\SnapshotFactory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DocumentController extends Controller
{
    public function index(Booking $booking): AnonymousResourceCollection
    {
        $this->authorize('view', $booking);

        $documents = $booking->documents()
            ->with('issuedBy')
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();

        return DocumentResource::collection($documents);
    }

    public function html(Booking $booking, DocumentKind $kind): Response
    {
        $this->authorize('view', $booking);

        if ($kind === DocumentKind::Receipt) {
            throw ValidationException::withMessages([
                'kind' => ['Preview a receipt at /receipts/{payment}/html.'],
            ]);
        }

        $snapshot = SnapshotFactory::build($booking, $kind, null, false);

        return response(DocumentHtml::render($kind, $snapshot, true), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function receiptHtml(Booking $booking, Payment $payment): Response
    {
        $this->authorize('view', $booking);

        if ((int) $payment->booking_id !== (int) $booking->id) {
            abort(404);
        }

        $snapshot = SnapshotFactory::build($booking, DocumentKind::Receipt, $payment, false);

        return response(DocumentHtml::render(DocumentKind::Receipt, $snapshot, true), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function issue(
        IssueDocumentRequest $request,
        Booking $booking,
        DocumentKind $kind,
        PrepareIssueDocument $action,
    ): DocumentResource {
        $this->authorize('issueDocument', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $payment = null;
        $paymentId = $request->validated('payment_id');

        if ($kind === DocumentKind::Receipt) {
            if ($paymentId === null) {
                throw ValidationException::withMessages([
                    'payment_id' => ['A receipt needs a payment.'],
                ]);
            }

            $payment = Payment::query()->findOrFail((int) $paymentId);
        }

        $reason = $request->validated('reason');

        return new DocumentResource($action->handle(
            $booking,
            $kind,
            is_string($reason) && $reason !== '' ? $reason : null,
            $payment,
            $actor,
        ));
    }

    public function issuedHtml(Document $document): Response
    {
        $this->authorize('view', $document->booking);

        return response(DocumentHtml::render($document->kind, $document->snapshot, true), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function send(SendDocumentRequest $request, Document $document, SendDocument $action): DeliveryResource
    {
        $this->authorize('issueDocument', $document->booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $existing = Delivery::query()
            ->where('idempotency_key', DeliveryKey::forDocument($document))
            ->first();

        $resend = $existing instanceof Delivery
            && in_array($existing->status, [DeliveryStatus::Sent, DeliveryStatus::Failed], true);

        return new DeliveryResource($action->handle(
            $document->booking,
            $document,
            $actor,
            resend: $resend,
        ));
    }

    public function file(Document $document): Response
    {
        $this->authorize('view', $document->booking);

        $bytes = Storage::disk('documents')->get($document->file_path);

        if (! is_string($bytes)) {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->kind->value.'-v'.$document->version.'.pdf"',
        ]);
    }
}
