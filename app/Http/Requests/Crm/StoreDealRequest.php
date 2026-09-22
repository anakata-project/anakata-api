<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\DealStage;
use App\Enums\DealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDealRequest extends FormRequest
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
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(DealType::class)],
            'stage' => ['required', 'string', Rule::in(array_map(
                fn (DealStage $stage): string => $stage->value,
                DealStage::open(),
            ))],
            'estimate' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
