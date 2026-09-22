<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Models\BookingAccessToken;
use App\Support\GuestExperience\QuestionnairePage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingAccessToken
 */
class QuestionnaireResource extends JsonResource
{
    /**
     * @return array{
     *     reference: string,
     *     departure_date: string,
     *     itinerary_name: string,
     *     questions: list<array{key: string, label: string, type: string, options: list<string>, restricted: bool, required: bool}>,
     *     guests: list<array{id: int, first_name: string, cabin: string, answers: array<string, string>}>
     * }
     */
    public function toArray(Request $request): array
    {
        return QuestionnairePage::forToken($this->resource);
    }
}
