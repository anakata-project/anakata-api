<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManifestIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => [
                'sometimes',
                'date_format:Y-m-d',
                Rule::when($this->filled('from'), ['after_or_equal:from']),
            ],
        ];
    }
}
