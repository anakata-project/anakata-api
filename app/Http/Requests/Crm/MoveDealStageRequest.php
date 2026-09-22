<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\DealStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveDealStageRequest extends FormRequest
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
            'stage' => ['required', 'string', Rule::in(array_map(
                fn (DealStage $stage): string => $stage->value,
                DealStage::stored(),
            ))],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
