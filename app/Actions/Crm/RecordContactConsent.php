<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\ConsentCapturePoint;
use App\Enums\ConsentPurpose;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\User;
use App\Support\History\History;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class RecordContactConsent extends Action
{
    public function handle(
        Contact $contact,
        ConsentPurpose $purpose,
        bool $granted,
        string $version,
        ConsentCapturePoint $capturePoint,
        ?CarbonInterface $capturedAt = null,
        ?string $ip = null,
        ?User $recordedBy = null,
        ?string $howObtained = null,
        ?int $sourceConsentId = null,
        ?string $sessionId = null,
    ): ContactConsent {
        if ($capturePoint === ConsentCapturePoint::Staff && ($howObtained === null || trim($howObtained) === '')) {
            throw ValidationException::withMessages([
                'how_obtained' => ['How the consent was obtained is required when staff record it.'],
            ]);
        }

        return $this->transaction(function () use (
            $contact,
            $purpose,
            $granted,
            $version,
            $capturePoint,
            $capturedAt,
            $ip,
            $recordedBy,
            $howObtained,
            $sourceConsentId,
            $sessionId,
        ): ContactConsent {
            if (is_string($sessionId) && $sessionId !== '') {
                $existing = ContactConsent::query()
                    ->where('contact_id', $contact->id)
                    ->where('purpose', $purpose)
                    ->where('session_id', $sessionId)
                    ->first();

                if ($existing instanceof ContactConsent) {
                    return $existing;
                }
            }

            $row = new ContactConsent;
            $row->contact_id = $contact->id;
            $row->purpose = $purpose;
            $row->granted = $granted;
            $row->version = $version;
            $row->captured_at = $capturedAt instanceof CarbonInterface
                ? Carbon::parse($capturedAt)
                : now();
            $row->ip = $capturePoint === ConsentCapturePoint::Staff ? null : $ip;
            $row->capture_point = $capturePoint;
            $row->recorded_by = $recordedBy instanceof User ? $recordedBy->id : null;
            $row->how_obtained = $capturePoint === ConsentCapturePoint::Staff ? $howObtained : null;
            $row->source_consent_id = $sourceConsentId;
            $row->session_id = $sessionId;
            $row->save();

            History::record($contact, 'contact.consent_changed', after: [
                'purpose' => $purpose->value,
                'granted' => $granted,
            ], actor: $recordedBy, system: ! $recordedBy instanceof User);

            return $row;
        });
    }
}
