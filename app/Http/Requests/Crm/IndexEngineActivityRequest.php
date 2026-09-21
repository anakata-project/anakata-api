<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\BehaviouralEventName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEngineActivityRequest extends FormRequest
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
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'name' => ['sometimes', Rule::enum(BehaviouralEventName::class)],
            'identified' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
