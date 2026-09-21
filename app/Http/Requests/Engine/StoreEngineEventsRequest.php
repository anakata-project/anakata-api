<?php

declare(strict_types=1);

namespace App\Http\Requests\Engine;

use App\Enums\BehaviouralEventName;
use App\Support\Engine\EngineSessionId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEngineEventsRequest extends FormRequest
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
            'session_id' => EngineSessionId::rules(required: true),
            'events' => ['required', 'array', 'min:1', 'max:25'],
            'events.*.event_id' => ['required', 'uuid'],
            'events.*.name' => ['required', 'string', Rule::in(BehaviouralEventName::acceptedFromClient())],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.params' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
