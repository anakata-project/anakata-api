<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Models\GuestResponse;
use App\Support\GuestExperience\SurveyAnswers;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuestResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record', GuestResponse::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return SurveyAnswers::rules(callNotes: true);
    }

    public function answers(): SurveyAnswers
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return SurveyAnswers::from($validated);
    }

    public function guestId(): int
    {
        return (int) $this->validated('guest_id');
    }
}
