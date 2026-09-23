<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\CharterEnquiryStatus;
use App\Enums\CharterProposalState;
use App\Enums\DocumentKind;
use App\Models\BookingAccessToken;
use App\Models\CharterEnquiry;
use App\Models\Document;
use App\Services\Config\CurrentConfig;
use App\Support\BusinessTime;
use App\Support\Crm\TaskDue;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CharterEnquiry
 */
class CharterEnquiryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     preferred_from: string|null,
     *     preferred_to: string|null,
     *     departure: array{id: int, date: string}|null,
     *     guests: int,
     *     contact: array{name: string, email: string|null, phone: string|null},
     *     message: string,
     *     source: string,
     *     status: CharterEnquiryStatus,
     *     proposal: array{version: int, number: string|null, state: CharterProposalState, valid_until: string|null}|null,
     *     sla_breached: bool,
     *     booking: array{id: int, reference: string}|null,
     *     created_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['contact', 'departure', 'booking']);

        $rules = app(CurrentConfig::class)->businessRules();
        $responseBy = TaskDue::responseHours($this->created_at, $rules);
        $open = in_array($this->status, [CharterEnquiryStatus::New, CharterEnquiryStatus::Contacted], true);
        $slaBreached = (bool) ($open && BusinessTime::now()->greaterThan($responseBy));

        return [
            'id' => $this->id,
            'preferred_from' => $this->preferred_from?->toDateString(),
            'preferred_to' => $this->preferred_to?->toDateString(),
            'departure' => $this->departure === null ? null : [
                'id' => $this->departure->id,
                'date' => $this->departure->date->toDateString(),
            ],
            'guests' => $this->guests,
            'contact' => [
                'name' => $this->contact->name,
                'email' => $this->contact->email,
                'phone' => $this->contact->phone,
            ],
            'message' => $this->message,
            'source' => $this->source->value,
            'status' => $this->status,
            'proposal' => $this->proposal(),
            'sla_breached' => $slaBreached,
            'booking' => $this->booking === null ? null : [
                'id' => $this->booking->id,
                'reference' => $this->booking->reference,
            ],
            'created_at' => Iso::utc($this->created_at),
        ];
    }

    /**
     * @return array{version: int, number: string|null, state: CharterProposalState, valid_until: string|null}|null
     */
    private function proposal(): ?array
    {
        $document = Document::query()
            ->where('charter_enquiry_id', $this->id)
            ->where('kind', DocumentKind::CharterProposal)
            ->orderByDesc('version')
            ->first();

        if (! $document instanceof Document) {
            return null;
        }

        $token = BookingAccessToken::query()
            ->where('document_id', $document->id)
            ->latest('id')
            ->first();
        $snapshot = $document->snapshot;
        $validUntil = null;

        if (isset($snapshot['valid_until']) && is_string($snapshot['valid_until'])) {
            $validUntil = $snapshot['valid_until'];
        }

        $state = match ($this->status) {
            CharterEnquiryStatus::Accepted => CharterProposalState::Accepted,
            CharterEnquiryStatus::Declined => CharterProposalState::Declined,
            default => $token instanceof BookingAccessToken && $token->isActive()
                ? CharterProposalState::Sent
                : CharterProposalState::Expired,
        };

        return [
            'version' => $document->version,
            'number' => $document->number,
            'state' => $state,
            'valid_until' => $validUntil,
        ];
    }
}
