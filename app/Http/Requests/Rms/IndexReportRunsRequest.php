<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class IndexReportRunsRequest extends FormRequest
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
            'definition' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
