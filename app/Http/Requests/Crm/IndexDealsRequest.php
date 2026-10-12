<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\DealStage;
use App\Enums\DealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDealsRequest extends FormRequest
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
            'owner' => ['sometimes', 'string', 'max:32'],
            'type' => ['sometimes', Rule::enum(DealType::class)],
            'stage' => ['sometimes', Rule::enum(DealStage::class)],
            'q' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
