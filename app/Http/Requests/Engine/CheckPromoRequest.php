<?php

declare(strict_types=1);

namespace App\Http\Requests\Engine;

use App\Http\Requests\Engine\Concerns\NormalizesEngineCabins;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckPromoRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64'],
            'departure_id' => ['required', 'integer', 'exists:departures,id'],
            ...$this->cabinPartyRules(),
            'guests' => ['required', 'array'],
            'guests.adults' => ['required', 'integer', 'min:0', 'max:36'],
            'guests.children' => ['required', 'integer', 'min:0', 'max:36'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateAndNormalizeCabins($validator);
    }
}
