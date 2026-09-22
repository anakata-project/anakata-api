<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Actions\GuestExperience\RecordGuestResponse;
use App\Actions\GuestExperience\ResolveSurveyAccessToken;
use App\Enums\GuestResponseSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engine\StoreSurveyResponseRequest;
use App\Http\Resources\Engine\SurveyResource;
use App\Support\Complete\CompleteAccess;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;

final class SurveyController extends Controller
{
    public function __construct(private readonly ResolveSurveyAccessToken $resolve) {}

    #[DocumentedResponse(status: 200, type: SurveyResource::class)]
    public function show(string $token): SurveyResource
    {
        return new SurveyResource($this->resolve->handle($token));
    }

    #[DocumentedResponse(status: 200, type: SurveyResource::class)]
    public function store(
        StoreSurveyResponseRequest $request,
        string $token,
        int $guest,
        RecordGuestResponse $action,
    ): SurveyResource {
        $access = $this->resolve->handle($token);
        $model = $this->resolve->guest($access, $guest);

        $action->handle(
            $access->booking,
            $model,
            $request->answers(),
            GuestResponseSource::GuestLink,
            actorLabel: CompleteAccess::ACTOR_LABEL,
        );

        $access->unsetRelation('booking');

        return new SurveyResource($this->resolve->handle($token));
    }
}
