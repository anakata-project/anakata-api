<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\ActivityKind;
use App\Rules\NoPersonalRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactActivityRequest extends FormRequest
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
            'kind' => ['required', 'string', Rule::in([
                ActivityKind::Call->value,
                ActivityKind::Email->value,
                ActivityKind::Meeting->value,
                ActivityKind::Note->value,
            ])],
            'body' => ['required', 'string', 'max:2000', new NoPersonalRecord],
            'deal_id' => ['sometimes', 'nullable', 'integer', 'exists:deals,id'],
        ];
    }
}
