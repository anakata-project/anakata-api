<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Consents\RecordConsent;
use App\Enums\ConsentDocument;
use App\Enums\ConsentSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\RecordConsentRequest;
use App\Http\Resources\Rms\BookingConsentResource;
use App\Http\Resources\Rms\ConsentResource;
use App\Models\Booking;
use App\Models\Consent;
use App\Models\User;
use App\Services\Config\CurrentConfig;
use App\Support\Consents\BookingConsentRow;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ConsentController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Rms\\BookingConsentResource>}',
    )]
    public function index(Booking $booking, CurrentConfig $config): AnonymousResourceCollection
    {
        $this->authorize('view', $booking);

        $booking->load('consents');
        $versions = $config->businessRules()->consentVersions;

        $rows = array_map(
            fn (ConsentDocument $document): BookingConsentRow => new BookingConsentRow(
                $document,
                $versions,
                $this->latestAccepted($booking, $document),
            ),
            ConsentDocument::checklist(),
        );

        return BookingConsentResource::collection($rows);
    }

    public function store(
        RecordConsentRequest $request,
        Booking $booking,
        RecordConsent $action,
    ): JsonResponse {
        $this->authorize('recordConsent', $booking);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $consent = $action->handle(
            $booking,
            ConsentDocument::from((string) $request->validated('document')),
            ConsentSource::Staff,
            howObtained: (string) $request->validated('how_obtained'),
            actor: $actor,
        );

        return (new ConsentResource($consent))->response()->setStatusCode(201);
    }

    private function latestAccepted(Booking $booking, ConsentDocument $document): ?Consent
    {
        $match = $booking->consents
            ->filter(
                fn (Consent $consent): bool => $consent->document === $document && ! $consent->withdrawn,
            )
            ->sortByDesc('id')
            ->first();

        return $match instanceof Consent ? $match : null;
    }
}
