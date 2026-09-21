<?php

declare(strict_types=1);

namespace App\Http\Requests\Engine;

use App\Enums\ConsentDocument;
use App\Support\Engine\EngineSessionId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordCompleteDeclarationsRequest extends FormRequest
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
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['required', 'string', Rule::enum(ConsentDocument::class), 'distinct'],
            'session_id' => EngineSessionId::rules(),
        ];
    }

    /**
     * @return list<ConsentDocument>
     */
    public function documents(): array
    {
        $values = $this->validated('documents');

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $value): ConsentDocument => ConsentDocument::from((string) $value),
            $values,
        ));
    }
}
