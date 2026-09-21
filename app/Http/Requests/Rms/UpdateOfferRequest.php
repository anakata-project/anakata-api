<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Http\Requests\Rms\Concerns\ValidatesOfferFields;
use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOfferRequest extends FormRequest
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
        $offer = $this->route('offer');

        return $this->offerFieldRules(
            required: false,
            existing: $offer instanceof Offer ? $offer : null,
        );
    }

    public function withValidator(Validator $validator): void
    {
        $offer = $this->route('offer');

        $this->withOfferGuardrails($validator, $offer instanceof Offer ? $offer : null);
    }
}
