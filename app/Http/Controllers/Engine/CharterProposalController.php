<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Actions\Charter\AcceptCharterProposal;
use App\Actions\Charter\DeclineCharterProposal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engine\AcceptCharterProposalRequest;
use App\Http\Requests\Engine\DeclineCharterProposalRequest;
use App\Http\Resources\Engine\CharterProposalAcceptedResource;
use App\Http\Resources\Engine\CharterProposalDeclinedResource;
use App\Http\Resources\Engine\CharterProposalViewResource;
use App\Models\BookingAccessToken;
use App\Services\Documents\DocumentView;

final class CharterProposalController extends Controller
{
    public function show(string $token, AcceptCharterProposal $proposals): CharterProposalViewResource
    {
        [$enquiry, $document] = $proposals->open($token, accepting: false);
        $access = BookingAccessToken::findByToken($token);
        $snapshot = $document->snapshot;
        $lines = is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [];

        return new CharterProposalViewResource([
            'html' => view(DocumentView::name($document->kind), ['snapshot' => $snapshot])->render(),
            'version' => $document->version,
            'number' => $document->number,
            'state' => $enquiry->status,
            'expired' => ! ($access instanceof BookingAccessToken && $access->isActive()),
            'price' => [
                'lines' => $lines,
                'total' => isset($snapshot['total']) ? (int) $snapshot['total'] : null,
                'deposit_pct' => isset($snapshot['deposit_pct']) ? (int) $snapshot['deposit_pct'] : null,
                'deposit' => isset($snapshot['deposit']) ? (int) $snapshot['deposit'] : null,
            ],
            'valid_until' => isset($snapshot['valid_until']) && is_string($snapshot['valid_until'])
                ? $snapshot['valid_until']
                : null,
        ]);
    }

    public function accept(
        AcceptCharterProposalRequest $request,
        string $token,
        AcceptCharterProposal $proposals,
    ): CharterProposalAcceptedResource {
        $booking = $proposals->handle(
            $token,
            (string) $request->validated('name'),
            $request->boolean('terms'),
            $request->ip(),
        );

        return new CharterProposalAcceptedResource([
            'booking_reference' => $booking->reference,
            'deposit_due_on' => $booking->deposit_due_on?->toDateString(),
        ]);
    }

    public function decline(
        DeclineCharterProposalRequest $request,
        string $token,
        DeclineCharterProposal $proposals,
    ): CharterProposalDeclinedResource {
        $reason = $request->validated('reason');
        $enquiry = $proposals->handle($token, is_string($reason) ? $reason : null);

        return new CharterProposalDeclinedResource([
            'status' => $enquiry->status,
        ]);
    }
}
