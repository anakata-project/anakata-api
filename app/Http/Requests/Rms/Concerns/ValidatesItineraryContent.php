<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms\Concerns;

use App\Support\Itineraries\Gradients;
use Illuminate\Validation\Rule;

trait ValidatesItineraryContent
{
    /**
     * ConvertEmptyStringsToNull turns "" into null; draft fields stay empty strings.
     *
     * @var list<string>
     */
    private const EMPTYABLE_STRINGS = [
        'name',
        'embark',
        'disembark',
        'tagline',
        'hero_alt',
        'card_description',
        'overview',
        'long_description',
        'meta_title',
        'meta_description',
    ];

    protected function restoreEmptyStrings(): void
    {
        $merge = [];

        foreach (self::EMPTYABLE_STRINGS as $key) {
            if ($this->exists($key) && $this->input($key) === null) {
                $merge[$key] = '';
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function contentRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:40'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
            'festive' => ['sometimes', 'boolean'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'nights' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'embark' => ['sometimes', 'string', 'max:255'],
            'disembark' => ['sometimes', 'string', 'max:255'],
            'tagline' => ['sometimes', 'string', 'max:255'],
            'hero_alt' => ['sometimes', 'string', 'max:255'],
            'fallback_gradient' => ['sometimes', 'string', Rule::in(Gradients::keys())],
            'card_description' => ['sometimes', 'string', 'max:220'],
            'overview' => ['sometimes', 'string'],
            'long_description' => ['sometimes', 'string'],
            'highlights' => ['sometimes', 'array'],
            'highlights.*' => ['required', 'string', 'min:1'],
            'chips' => ['sometimes', 'array'],
            'chips.*' => ['required', 'string', 'min:1'],
            'facts' => ['sometimes', 'array', 'size:6'],
            'facts.*' => ['required', 'array', 'size:2'],
            'facts.*.0' => ['required', 'string', 'min:1'],
            'facts.*.1' => ['required', 'string', 'min:1'],
            'day_plan' => ['sometimes', 'array'],
            'day_plan.*' => ['required', 'array', 'size:2'],
            'day_plan.*.0' => ['required', 'string', 'min:1'],
            'day_plan.*.1' => ['required', 'string', 'min:1'],
            'included' => ['sometimes', 'array'],
            'included.*' => ['required', 'string', 'min:1'],
            'excluded' => ['sometimes', 'array'],
            'excluded.*' => ['required', 'string', 'min:1'],
            'faqs' => ['sometimes', 'array'],
            'faqs.*' => ['required', 'array', 'size:2'],
            'faqs.*.0' => ['required', 'string', 'min:1'],
            'faqs.*.1' => ['required', 'string', 'min:1'],
            'slug' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9-]+$/'],
            'meta_title' => ['sometimes', 'string', 'max:60'],
            'meta_description' => ['sometimes', 'string', 'max:155'],
        ];
    }
}
