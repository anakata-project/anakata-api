<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Http\Requests\Rms\Concerns\ValidatesOfferFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOfferRequest extends FormRequest
{
    use ValidatesOfferFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareOfferFields();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->offerFieldRules(required: true);
    }

    public function withValidator(Validator $validator): void
    {
        $this->withOfferGuardrails($validator);
    }
}
