<?php

declare(strict_types=1);

namespace App\Actions\Engine;

use App\Actions\Action;
use App\Actions\Crm\RecordContactConsent;
use App\Enums\ConsentCapturePoint;
use App\Enums\ConsentPurpose;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Services\Config\CurrentConfig;
use App\Support\Journeys\JourneyEngine;
use App\Support\Templates\UnsubscribeLink;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UnsubscribeContact extends Action
{
    public function __construct(
        private readonly RecordContactConsent $consents,
        private readonly JourneyEngine $journeys,
        private readonly CurrentConfig $config,
    ) {}

    public function resolve(string $token): Contact
    {
        $contact = Contact::query()->where('unsubscribe_token', $token)->first();

        if (! $contact instanceof Contact || ! hash_equals(UnsubscribeLink::token($contact), $token)) {
            throw new HttpException(404, 'This link is not valid.');
        }

        return $contact;
    }

    /**
     * @return array{valid: bool, already_unsubscribed: bool}
     */
    public function preview(string $token): array
    {
        $contact = $this->resolve($token);

        return [
            'valid' => true,
            'already_unsubscribed' => $this->alreadyWithdrawn($contact),
        ];
    }

    /**
     * @return array{valid: bool, already_unsubscribed: bool}
     */
    public function withdraw(string $token, ?string $ip): array
    {
        $contact = $this->resolve($token);

        if ($this->alreadyWithdrawn($contact)) {
            return [
                'valid' => true,
                'already_unsubscribed' => true,
            ];
        }

        $this->transaction(function () use ($contact, $ip): void {
            $this->consents->handle(
                $contact,
                ConsentPurpose::Marketing,
                granted: false,
                version: $this->config->businessRules()->consentVersions->checkoutMarketing,
                capturePoint: ConsentCapturePoint::Unsubscribe,
                ip: $ip,
            );

            $this->journeys->exitUnsubscribed($contact);
        });

        return [
            'valid' => true,
            'already_unsubscribed' => true,
        ];
    }

    private function alreadyWithdrawn(Contact $contact): bool
    {
        $latest = ContactConsent::query()
            ->where('contact_id', $contact->id)
            ->where('purpose', ConsentPurpose::Marketing)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        return $latest instanceof ContactConsent && ! $latest->granted;
    }
}
