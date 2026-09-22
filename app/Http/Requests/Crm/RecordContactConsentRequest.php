<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\ConsentPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordContactConsentRequest extends FormRequest
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
            'purpose' => ['required', 'string', Rule::enum(ConsentPurpose::class)],
            'granted' => ['required', 'boolean'],
            'how_obtained' => ['required', 'string', 'max:2000'],
            'version' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    public function purpose(): ConsentPurpose
    {
        return ConsentPurpose::from((string) $this->validated('purpose'));
    }

    public function granted(): bool
    {
        return (bool) $this->validated('granted');
    }
}
