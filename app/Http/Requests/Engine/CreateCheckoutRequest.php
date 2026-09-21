<?php

declare(strict_types=1);

namespace App\Http\Requests\Engine;

use App\Http\Requests\Engine\Concerns\NormalizesEngineCabins;
use App\Support\Engine\EnginePartyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateCheckoutRequest extends FormRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateAndNormalizeCabins($validator);

        $validator->after(function (Validator $after): void {
            if ($after->errors()->isNotEmpty()) {
                return;
            }

            app(EnginePartyRules::class)->apply($after, $this->cabinRows());
        });
    }
}
