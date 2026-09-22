<?php

declare(strict_types=1);

namespace App\Http\Requests\Privacy;

use App\Enums\SubjectRequestChannel;
use App\Enums\SubjectRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequestRequest extends FormRequest
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
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'type' => ['required', Rule::enum(SubjectRequestType::class)],
            'received_at' => ['required', 'date'],
            'channel' => ['required', Rule::enum(SubjectRequestChannel::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
