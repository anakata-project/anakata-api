<?php

declare(strict_types=1);

namespace App\Actions\Charter;

use App\Actions\Action;
use App\Enums\CharterEnquiryStatus;
use App\Exceptions\ConflictException;
use App\Models\CharterEnquiry;
use App\Support\History\History;

final class DeclineCharterProposal extends Action
{
    public function __construct(private readonly AcceptCharterProposal $proposals) {}

    public function handle(string $plainToken, ?string $reason): CharterEnquiry
    {
        return $this->transaction(function () use ($plainToken, $reason): CharterEnquiry {
            [$enquiry, $document] = $this->proposals->open($plainToken, accepting: true);

            if ($enquiry->status === CharterEnquiryStatus::Declined) {
                throw new ConflictException('This proposal has already been declined.');
            }

            $enquiry->status = CharterEnquiryStatus::Declined;
            $enquiry->save();

            History::record($enquiry, 'charter_enquiry.declined', after: [
                'document_version' => $document->version,
            ], reason: $reason !== null && trim($reason) !== '' ? trim($reason) : null, system: true);

            return $enquiry->refresh();
        });
    }
}
