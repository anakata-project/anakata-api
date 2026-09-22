<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Models\BookingAccessToken;
use App\Support\GuestExperience\SurveyPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingAccessToken
 */
class SurveyResource extends JsonResource
{
    /**
     * @return array{
     *     reference: string,
     *     departure_date: string,
     *     itinerary_name: string,
     *     guests: list<array{id: int, first_name: string, last_name: string, responded: bool}>
     * }
     */
    public function toArray(Request $request): array
    {
        return SurveyPage::forToken($this->resource);
    }
}
