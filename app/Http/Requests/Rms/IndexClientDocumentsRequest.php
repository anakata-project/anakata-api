<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\DocumentPlanKind;
use App\Enums\DocumentPlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientDocumentsRequest extends FormRequest
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
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'kind' => ['sometimes', Rule::enum(DocumentPlanKind::class)],
            'status' => ['sometimes', Rule::enum(DocumentPlanStatus::class)],
            'q' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
