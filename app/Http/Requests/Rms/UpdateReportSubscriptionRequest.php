<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportSubscriptionRequest extends FormRequest
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
        return [
            'active' => ['sometimes', 'boolean'],
            'send_at' => ['sometimes', 'date_format:H:i'],
            'weekday' => ['sometimes', 'nullable', 'integer', 'between:1,7'],
            'day_of_month' => ['sometimes', 'nullable', 'integer', 'between:1,28'],
        ];
    }
}
