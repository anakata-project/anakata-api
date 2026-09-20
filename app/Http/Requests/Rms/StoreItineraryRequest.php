<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Http\Requests\Rms\Concerns\ValidatesItineraryContent;
use Illuminate\Foundation\Http\FormRequest;

class StoreItineraryRequest extends FormRequest
{
    use ValidatesItineraryContent;

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
            ...$this->contentRules(),
            'code' => ['required', 'string', 'regex:/^[A-Z0-9_]{2,10}$/', 'unique:itineraries,code'],
            'slug' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9-]+$/', 'unique:itineraries,slug'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->restoreEmptyStrings();

        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => strtoupper($code)]);
        }

        if ($this->exists('slug') && $this->input('slug') === '') {
            $this->merge(['slug' => null]);
        }
    }
}
