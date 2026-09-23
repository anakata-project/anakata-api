<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\ChannelOfOriginGroup;
use App\Support\Metrics\MetricScope;
use App\Support\Metrics\MetricWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetricsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['yacht', 'itinerary', 'channel', 'agency'] as $key) {
            if ($this->input($key) === '') {
                $merged[$key] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'yacht' => ['nullable', 'integer', 'exists:yachts,id'],
            'itinerary' => ['nullable', 'integer', 'exists:itineraries,id'],
            'channel' => ['nullable', Rule::enum(ChannelOfOriginGroup::class)],
            'agency' => ['nullable', 'integer', 'exists:agencies,id'],
        ];
    }

    public function window(): MetricWindow
    {
        return new MetricWindow(
            (string) $this->validated('from'),
            (string) $this->validated('to'),
        );
    }

    public function scope(): MetricScope
    {
        $channel = $this->validated('channel');

        return new MetricScope(
            yachtId: $this->filled('yacht') ? $this->integer('yacht') : null,
            itineraryId: $this->filled('itinerary') ? $this->integer('itinerary') : null,
            channel: is_string($channel) ? ChannelOfOriginGroup::from($channel) : null,
            agencyId: $this->filled('agency') ? $this->integer('agency') : null,
        );
    }
}
