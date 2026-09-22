<?php

declare(strict_types=1);

namespace App\Actions\Consents;

use App\Actions\Action;
use App\Actions\Crm\RecordContactConsent;
use App\Enums\ConsentCapturePoint;
use App\Enums\ConsentDocument;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentSource;
use App\Models\Booking;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\User;
use App\Services\Config\CurrentConfig;
use App\Support\History\History;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class RecordConsent extends Action
{
    public function __construct(private readonly CurrentConfig $config) {}

    public function handle(
        Booking $booking,
        ConsentDocument $document,
        ConsentSource $source,
        ?string $howObtained = null,
        ?string $ip = null,
        ?CarbonInterface $acceptedAt = null,
        ?string $version = null,
        ?User $actor = null,
        ?string $actorLabel = null,
        bool $withdrawn = false,
    ): Consent {
        if ($source === ConsentSource::Staff && ($howObtained === null || $howObtained === '')) {
            throw ValidationException::withMessages([
                'how_obtained' => ['How the consent was obtained is required when recording it as staff.'],
            ]);
        }

        $version ??= $this->config->businessRules()->consentVersions->for($document);

        return $this->transaction(function () use (
            $booking,
            $document,
            $source,
            $howObtained,
            $ip,
            $acceptedAt,
            $version,
            $actor,
            $actorLabel,
            $withdrawn,
        ): Consent {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            $latest = Consent::query()
                ->where('booking_id', $locked->id)
                ->where('document', $document)
                ->orderByDesc('id')
                ->first();

            if (
                ! $withdrawn
                && $latest instanceof Consent
                && ! $latest->withdrawn
                && $latest->version === $version
            ) {
                return $latest;
            }

            $consent = new Consent;
            $consent->booking_id = $locked->id;
            $consent->document = $document;
            $consent->version = $version;
            $consent->accepted_at = $acceptedAt instanceof CarbonInterface
                ? Carbon::parse($acceptedAt)
                : now();
            $consent->ip = $source === ConsentSource::Staff ? null : $ip;
            $consent->source = $source;
            $consent->recorded_by = $source === ConsentSource::Staff && $actor instanceof User
                ? $actor->id
                : null;
            $consent->how_obtained = $source === ConsentSource::Staff ? $howObtained : null;
            $consent->withdrawn = $withdrawn;
            $consent->save();

            if ($document === ConsentDocument::Marketing) {
                $contact = Contact::query()->find($locked->contact_id);

                if ($contact instanceof Contact) {
                    app(RecordContactConsent::class)->handle(
                        $contact,
                        ConsentPurpose::Marketing,
                        granted: ! $withdrawn,
                        version: $version,
                        capturePoint: match ($source) {
                            ConsentSource::Staff => ConsentCapturePoint::Staff,
                            ConsentSource::Engine, ConsentSource::PaymentLink => ConsentCapturePoint::EngineForm,
                        },
                        capturedAt: $consent->accepted_at,
                        ip: $consent->ip,
                        recordedBy: $actor,
                        howObtained: $howObtained,
                        sourceConsentId: $consent->id,
                    );
                }
            }

            History::record($locked, 'consent.recorded', after: [
                'document' => $document->value,
                'version' => $version,
                'source' => $source->value,
            ], reason: $source === ConsentSource::Staff ? $howObtained : null, actor: $actor, extraContext: [
                'what' => 'Consent recorded — '.$document->label(),
            ], actorLabel: $actorLabel);

            return $consent;
        });
    }
}
