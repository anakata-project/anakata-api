<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Charter\IssueCharterProposal;
use App\Actions\Charter\UpdateCharterEnquiryStatus;
use App\Enums\CharterEnquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\IndexCharterEnquiriesRequest;
use App\Http\Requests\Rms\IssueCharterProposalRequest;
use App\Http\Requests\Rms\UpdateCharterEnquiryRequest;
use App\Http\Resources\Rms\CharterEnquiryResource;
use App\Http\Resources\Rms\DocumentResource;
use App\Models\CharterEnquiry;
use App\Models\User;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CharterEnquiryController extends Controller
{
    #[DocumentedResponse(status: 200, type: 'array<CharterEnquiryResource>')]
    public function index(IndexCharterEnquiriesRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CharterEnquiry::class);

        $enquiries = CharterEnquiry::query()
            ->with(['contact', 'departure'])
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('status', $request->validated('status')),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return CharterEnquiryResource::collection($enquiries);
    }

    #[DocumentedResponse(status: 200, type: CharterEnquiryResource::class)]
    public function update(
        UpdateCharterEnquiryRequest $request,
        CharterEnquiry $enquiry,
        UpdateCharterEnquiryStatus $action,
    ): CharterEnquiryResource {
        $this->authorize('update', $enquiry);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $status = $request->validated('status');
        $status = $status instanceof CharterEnquiryStatus
            ? $status
            : CharterEnquiryStatus::from((string) $status);

        $reason = $request->validated('reason');

        return new CharterEnquiryResource($action->handle(
            $enquiry,
            $status,
            $actor,
            is_string($reason) ? $reason : null,
        ));
    }

    #[DocumentedResponse(status: 201, type: DocumentResource::class)]
    public function proposal(
        IssueCharterProposalRequest $request,
        CharterEnquiry $enquiry,
        IssueCharterProposal $action,
    ): DocumentResource {
        $this->authorize('update', $enquiry);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $reason = $request->validated('reason');
        $document = $action->handle($enquiry, $actor, is_string($reason) ? $reason : null);

        return new DocumentResource($document);
    }
}
