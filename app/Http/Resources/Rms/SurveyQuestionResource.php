<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\SurveyQuestionType;
use App\Support\GuestExperience\SurveyQuestion;
use App\Support\GuestExperience\SurveyQuestions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SurveyQuestion
 */
class SurveyQuestionResource extends JsonResource
{
    /**
     * @return array{key: string, label: string, type: SurveyQuestionType, min: int|null, max: int|null}
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }

    /**
     * @return list<SurveyQuestion>
     */
    public static function questions(): array
    {
        return SurveyQuestions::all();
    }
}
