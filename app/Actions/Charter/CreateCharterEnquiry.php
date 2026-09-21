<?php

declare(strict_types=1);

namespace App\Actions\Charter;

use App\Actions\Action;
use App\Actions\Contacts\ResolveContact;
use App\Actions\Contacts\StitchEngineIdentity;
use App\Enums\CharterEnquirySource;
use App\Enums\CharterEnquiryStatus;
use App\Enums\ContactType;
use App\Mail\CharterEnquiryMail;
use App\Models\CharterEnquiry;
use App\Support\History\History;
use Illuminate\Support\Facades\Mail;

final class CreateCharterEnquiry extends Action
{
    public function __construct(
        private readonly ResolveContact $contacts,
        private readonly StitchEngineIdentity $identity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): CharterEnquiry
    {
        $enquiry = $this->transaction(function () use ($data): CharterEnquiry {
            $payload = is_array($data['contact'] ?? null) ? $data['contact'] : [];
            $payload['type'] = ContactType::CorporateCharter;
            $contact = $this->contacts->handle($payload);

            $this->identity->handle(
                $contact,
                isset($data['session_id']) && is_string($data['session_id']) ? $data['session_id'] : null,
            );

            $enquiry = CharterEnquiry::query()->create([
                'preferred_from' => $data['preferred_from'] ?? null,
                'preferred_to' => $data['preferred_to'] ?? null,
                'departure_id' => isset($data['departure_id']) ? (int) $data['departure_id'] : null,
                'guests' => (int) $data['guests'],
                'contact_id' => $contact->id,
                'message' => (string) $data['message'],
                'source' => CharterEnquirySource::Engine,
                'status' => CharterEnquiryStatus::New,
            ]);

            History::record($enquiry, 'charter_enquiry.created', after: [
                'guests' => $enquiry->guests,
                'departure_id' => $enquiry->departure_id,
                'contact_id' => $enquiry->contact_id,
                'source' => $enquiry->source->value,
            ], system: true);

            return $enquiry->refresh()->load(['contact', 'departure']);
        });

        Mail::to((string) config('mail.reservations'))->send(new CharterEnquiryMail($enquiry));

        return $enquiry;
    }
}
