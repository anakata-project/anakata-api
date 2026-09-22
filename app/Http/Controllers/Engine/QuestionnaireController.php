<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Actions\GuestExperience\RecordGuestPreferences;
use App\Actions\GuestExperience\ResolveQuestionnaireAccessToken;
use App\Enums\PreferenceSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engine\UpdateQuestionnaireRequest;
use App\Http\Resources\Engine\QuestionnaireResource;
use App\Support\Complete\CompleteAccess;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;

final class QuestionnaireController extends Controller
{
    public function __construct(private readonly ResolveQuestionnaireAccessToken $resolve) {}

    #[DocumentedResponse(status: 200, type: QuestionnaireResource::class)]
    public function show(string $token): QuestionnaireResource
    {
        return new QuestionnaireResource($this->resolve->handle($token));
    }

    #[DocumentedResponse(status: 200, type: QuestionnaireResource::class)]
    public function update(
        UpdateQuestionnaireRequest $request,
        string $token,
        int $guest,
        RecordGuestPreferences $action,
    ): QuestionnaireResource {
        $access = $this->resolve->handle($token);
        $model = $this->resolve->guest($access, $guest);

        $action->handle(
            $model,
            $request->answers(),
            PreferenceSource::GuestLink,
            canWriteRestricted: true,
            actorLabel: CompleteAccess::ACTOR_LABEL,
        );

        $access->unsetRelation('booking');

        return new QuestionnaireResource($this->resolve->handle($token));
    }
}
