<?php

declare(strict_types=1);

namespace App\Http\Requests\Engine;

use App\Support\GuestExperience\SurveyAnswers;
use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return SurveyAnswers::rules(callNotes: false);
    }

    public function answers(): SurveyAnswers
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return SurveyAnswers::from($validated);
    }
}
