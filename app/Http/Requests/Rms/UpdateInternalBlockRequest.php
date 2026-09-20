<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\BlockReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInternalBlockRequest extends FormRequest
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
            'reason' => ['sometimes', Rule::enum(BlockReason::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
