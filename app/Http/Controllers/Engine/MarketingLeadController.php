<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Actions\Engine\CaptureMarketingLead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engine\StoreMarketingLeadRequest;
use App\Http\Resources\Engine\MarketingLeadResource;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;

final class MarketingLeadController extends Controller
{
    #[DocumentedResponse(status: 200, type: MarketingLeadResource::class)]
    public function store(StoreMarketingLeadRequest $request, CaptureMarketingLead $action): MarketingLeadResource
    {
        /** @var array{email: string, first_name: string, version: string, session_id?: string|null} $data */
        $data = $request->safe()->only(['email', 'first_name', 'version', 'session_id']);

        $action->handle($data, $request->ip());

        return new MarketingLeadResource(['accepted' => true]);
    }
}
