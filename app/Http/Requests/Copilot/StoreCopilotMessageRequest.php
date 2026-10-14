<?php

declare(strict_types=1);

namespace App\Http\Requests\Copilot;

use Illuminate\Foundation\Http\FormRequest;

class StoreCopilotMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:8000'],
            'client_message_id' => ['required', 'uuid'],
        ];
    }
}
