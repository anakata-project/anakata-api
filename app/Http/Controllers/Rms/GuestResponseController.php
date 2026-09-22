<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\GuestExperience\RecordGuestResponse;
use App\Enums\GuestResponseSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\NpsIndexRequest;
use App\Http\Requests\Rms\StoreGuestResponseRequest;
use App\Models\Booking;
use App\Models\Guest;
use App\Support\GuestExperience\NpsDashboard;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class GuestResponseController extends Controller
{
    public function index(NpsIndexRequest $request, NpsDashboard $dashboard): JsonResponse
    {
        $from = $request->validated('from');
        $to = $request->validated('to');

        return response()->json($dashboard->present(
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        ));
    }

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

        return response()->json([
            'id' => $response->id,
            'guest_id' => $response->guest_id,
            'score' => $response->score,
            'recommend' => $response->recommend,
            'call_notes' => $response->call_notes,
        ], 201);
    }
}
