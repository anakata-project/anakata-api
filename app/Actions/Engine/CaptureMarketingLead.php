<?php

declare(strict_types=1);

namespace App\Actions\Engine;

use App\Actions\Action;
use App\Actions\Contacts\ResolveContact;
use App\Actions\Contacts\StitchEngineIdentity;
use App\Actions\Crm\RecordContactConsent;
use App\Enums\ConsentCapturePoint;
use App\Enums\ConsentPurpose;
use App\Models\JourneyEnrolment;
use App\Services\Config\CurrentConfig;
use Illuminate\Validation\ValidationException;

final class CaptureMarketingLead extends Action
{
    public function __construct(
        private readonly ResolveContact $contacts,
        private readonly RecordContactConsent $consents,
        private readonly StitchEngineIdentity $stitch,
        private readonly CurrentConfig $config,
    ) {}

    /**
     * @param  array{email: string, first_name: string, version: string, session_id?: string|null}  $data
     */
    public function handle(array $data, ?string $ip): void
    {
        $version = $this->config->businessRules()->consentVersions->checkoutMarketing;

        if ($data['version'] !== $version) {
            throw ValidationException::withMessages([
                'version' => ['The consent text version is not the current checkout marketing version.'],
            ]);
        }

        $contact = $this->contacts->handle([
            'name' => $data['first_name'],
            'email' => $data['email'],
        ]);

        $this->consents->handle(
            $contact,
            ConsentPurpose::Marketing,
            granted: true,
            version: $version,
            capturePoint: ConsentCapturePoint::EngineForm,
            ip: $ip,
            sessionId: $data['session_id'] ?? null,
        );

        $sessionId = $data['session_id'] ?? null;

        if (is_string($sessionId) && $sessionId !== '') {
            $this->stitch->handle($contact, $sessionId);
        }

        JourneyEnrolment::onLeadCaptured($contact);
    }
}
