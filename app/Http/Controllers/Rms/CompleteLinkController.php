<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Complete\IssueCompleteAccessToken;
use App\Http\Controllers\Controller;
use App\Http\Resources\Rms\CompleteLinkResource;
use App\Models\Booking;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;

final class CompleteLinkController extends Controller
{
    #[DocumentedResponse(status: 200, type: CompleteLinkResource::class)]
    public function __invoke(Booking $booking, IssueCompleteAccessToken $action): CompleteLinkResource
    {
        $this->authorize('issueCompleteLink', $booking);

        return new CompleteLinkResource([
            'url' => $action->handle($booking),
        ]);
    }
}
