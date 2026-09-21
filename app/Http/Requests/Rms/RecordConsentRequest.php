<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\ConsentDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordConsentRequest extends FormRequest
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
            'document' => ['required', Rule::enum(ConsentDocument::class)],
            'how_obtained' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }
}
