<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\GuestExperience\RecordGuestResponse;
use App\Enums\GuestResponseSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\NpsIndexRequest;
use App\Http\Requests\Rms\StoreGuestResponseRequest;
use App\Http\Resources\Rms\GuestResponseResource;
use App\Http\Resources\Rms\NpsViewResource;
use App\Http\Resources\Rms\SurveyGuestResource;
use App\Http\Resources\Rms\SurveyQuestionResource;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\GuestResponse;
use App\Support\GuestExperience\NpsDashboard;
use App\Support\GuestExperience\SurveyGuests;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class GuestResponseController extends Controller
{
    #[DocumentedResponse(status: 200, type: SurveyGuestResource::class)]
    public function surveyGuests(Booking $booking): AnonymousResourceCollection
    {
        $this->authorize('view', $booking);
        $this->authorize('record', GuestResponse::class);

        return SurveyGuestResource::collection(SurveyGuests::forBooking($booking));
    }

    #[DocumentedResponse(status: 200, type: SurveyQuestionResource::class)]
    public function questions(): AnonymousResourceCollection
    {
        $this->authorize('viewQuestions', GuestResponse::class);

        return SurveyQuestionResource::collection(SurveyQuestionResource::questions());
    }

    public function index(NpsIndexRequest $request, NpsDashboard $dashboard): NpsViewResource
    {
        $from = $request->validated('from');
        $to = $request->validated('to');

        return new NpsViewResource($dashboard->present(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        ));
    }

    #[DocumentedResponse(status: 201, type: GuestResponseResource::class)]
    public function store(StoreGuestResponseRequest $request, Booking $booking, RecordGuestResponse $action): JsonResponse
    {
        $this->authorize('view', $booking);

        $guest = Guest::query()->whereKey($request->guestId())->first();

        if (! $guest instanceof Guest) {
            throw new HttpException(422, 'That guest is not on this booking.');
        }

        $response = $action->handle(
            $booking,
            $guest,
            $request->answers(),
            GuestResponseSource::Staff,
            $request->user(),
        );

        return (new GuestResponseResource($response))->response()->setStatusCode(201);
    }
}
