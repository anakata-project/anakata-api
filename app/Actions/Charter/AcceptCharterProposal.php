<?php

declare(strict_types=1);

namespace App\Actions\Charter;

use App\Actions\Action;
use App\Actions\Bookings\CreateReservation;
use App\Actions\Consents\RecordConsent;
use App\Enums\BookingAccessTokenPurpose;
use App\Enums\CharterEnquiryStatus;
use App\Enums\ConsentDocument;
use App\Enums\ConsentSource;
use App\Enums\DocumentKind;
use App\Exceptions\ConflictException;
use App\Models\Booking;
use App\Models\BookingAccessToken;
use App\Models\CharterEnquiry;
use App\Models\Document;
use App\Models\User;
use App\Services\Config\CurrentConfig;
use App\Support\BusinessHours;
use App\Support\BusinessTime;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AcceptCharterProposal extends Action
{
    public function __construct(
        private readonly CreateReservation $reservations,
        private readonly RecordConsent $consents,
        private readonly CurrentConfig $config,
    ) {}

    public function handle(string $plainToken, string $name, bool $terms, ?string $ip): Booking
    {
        $name = trim($name);

        if ($name === '' || ! $terms) {
            $errors = [];

            if ($name === '') {
                $errors['name'] = ['Type your name to accept.'];
            }

            if (! $terms) {
                $errors['terms'] = ['The proposal terms must be accepted.'];
            }

            throw ValidationException::withMessages($errors);
        }

        return $this->transaction(function () use ($plainToken, $name, $ip): Booking {
            [$enquiry, $document, $actor] = $this->open($plainToken, accepting: true);

            $created = $this->reservations->handle([
                'departure_id' => $enquiry->departure_id,
                'type' => 'CHARTER',
                'back_to_back' => false,
                'cabins' => [[
                    'adults' => $enquiry->guests,
                    'children' => 0,
                ]],
                'client' => [
                    'name' => $enquiry->contact->name,
                    'email' => $enquiry->contact->email,
                ],
                'main_channel' => 'D2C',
                'channel_of_origin' => 'Hotel Booking Engine',
                'group' => null,
            ], $actor);

            $booking = $created->bookings->first();

            if (! $booking instanceof Booking) {
                throw new ConflictException('The charter booking was not created.');
            }

            $snapshot = $document->snapshot;
            $total = (int) ($snapshot['total'] ?? $booking->total);
            $lines = $snapshot['lines'] ?? null;

            if ($booking->total !== $total) {
                $booking->total = $total;
                $booking->deposit_pct = (int) ($snapshot['deposit_pct'] ?? $booking->deposit_pct);

                if (is_array($lines)) {
                    $booking->price_lines = $lines;
                }
            }

            $rules = $this->config->businessRules();
            $acceptedAt = BusinessTime::now();
            $due = BusinessHours::fromDocument($rules)
                ->endOfNthBusinessDay($acceptedAt, $rules->charter->depositBusinessDays);
            $booking->deposit_due_on = $due->setTimezone(BusinessTime::zone())->startOfDay();
            $booking->save();

            $version = trim((string) ($document->number ?? 'proposal')).' v'.$document->version;

            $this->consents->handle(
                $booking,
                ConsentDocument::CharterProposal,
                ConsentSource::Engine,
                null,
                $ip,
                $acceptedAt,
                $version,
                $actor,
            );

            $enquiry->status = CharterEnquiryStatus::Accepted;
            $enquiry->accepted_at = $acceptedAt;
            $enquiry->accepted_name = $name;
            $enquiry->booking_id = $booking->id;
            $enquiry->save();

            History::record($enquiry, 'charter_enquiry.accepted', after: [
                'booking_id' => $booking->id,
                'accepted_name' => $name,
                'document_version' => $document->version,
            ], actor: $actor);

            return $booking->refresh();
        });
    }

    /**
     * @return array{0: CharterEnquiry, 1: Document, 2: User}
     */
    public function open(string $plainToken, bool $accepting): array
    {
        $token = BookingAccessToken::findByToken($plainToken);

        if (
            ! $token instanceof BookingAccessToken
            || $token->purpose !== BookingAccessTokenPurpose::CharterProposal
            || $token->document_id === null
        ) {
            abort(404);
        }

        $document = Document::query()->find($token->document_id);
        $enquiry = CharterEnquiry::query()->find($token->charter_enquiry_id);

        if (! $document instanceof Document || ! $enquiry instanceof CharterEnquiry) {
            abort(404);
        }

        $enquiry->load(['contact', 'departure']);

        $latest = Document::query()
            ->where('charter_enquiry_id', $enquiry->id)
            ->where('kind', DocumentKind::CharterProposal)
            ->orderByDesc('version')
            ->first();

        $current = $latest instanceof Document && $latest->id === $document->id;

        if ($accepting && (! $token->isActive() || ! $current)) {
            throw new HttpException(410, 'A new proposal is needed.');
        }

        if ($accepting && $enquiry->status === CharterEnquiryStatus::Accepted) {
            throw new ConflictException('This proposal has already been accepted.');
        }

        if ($accepting && $enquiry->status !== CharterEnquiryStatus::Quoted) {
            throw new ConflictException('This proposal cannot be accepted.');
        }

        $actor = $document->issuedBy;

        if ($accepting && ! $actor instanceof User) {
            throw new ConflictException('This proposal has no issuer.');
        }

        if (! $actor instanceof User) {
            abort(404);
        }

        return [$enquiry, $document, $actor];
    }
}
