<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\ConversationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ConversationStatus::class)],
        ];
    }

    public function status(): ConversationStatus
    {
        return ConversationStatus::from($this->string('status')->toString());
    }
}
