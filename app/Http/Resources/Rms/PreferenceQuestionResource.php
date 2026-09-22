<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Support\GuestExperience\PreferenceQuestion;
use App\Support\GuestExperience\PreferenceQuestions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PreferenceQuestion
 */
class PreferenceQuestionResource extends JsonResource
{
    /**
     * @return array{key: string, label: string, type: string, options: list<string>, restricted: bool, required: bool}
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }

    /**
     * @return list<PreferenceQuestion>
     */
    public static function questions(): array
    {
        return PreferenceQuestions::all();
    }
}
