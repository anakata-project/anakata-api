<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\ConversationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexConversationsRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', Rule::enum(ConversationStatus::class)],
            'unread' => ['sometimes', 'boolean'],
            'contact_id' => ['sometimes', 'integer', 'exists:contacts,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function status(): ?ConversationStatus
    {
        $status = $this->validated('status');

        return is_string($status) ? ConversationStatus::from($status) : null;
    }

    public function unread(): ?bool
    {
        if (! $this->exists('unread') || $this->input('unread') === null || $this->input('unread') === '') {
            return null;
        }

        return $this->boolean('unread');
    }

    public function contactId(): ?int
    {
        $contactId = $this->validated('contact_id');

        return is_numeric($contactId) ? (int) $contactId : null;
    }

    public function perPage(): int
    {
        $perPage = $this->integer('per_page', 25);

        return min(100, max(1, $perPage));
    }
}
