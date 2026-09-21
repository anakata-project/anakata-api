<?php

declare(strict_types=1);

namespace App\Http\Requests\Engine;

use App\Http\Requests\Engine\Concerns\NormalizesEngineCabins;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EngineQuoteRequest extends FormRequest
{
    use NormalizesEngineCabins;

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
            'departure_id' => ['required', 'integer', 'exists:departures,id'],
            ...$this->cabinPartyRules(),
            'online_deposit' => ['sometimes', 'boolean'],
            'promo_code' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateAndNormalizeCabins($validator);
    }
}
