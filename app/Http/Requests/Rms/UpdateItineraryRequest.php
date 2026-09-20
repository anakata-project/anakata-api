<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\ItineraryStatus;
use App\Http\Requests\Rms\Concerns\ValidatesItineraryContent;
use App\Models\Itinerary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItineraryRequest extends FormRequest
{
    use ValidatesItineraryContent;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $itinerary = $this->route('itinerary');
        $itineraryId = $itinerary instanceof Itinerary ? $itinerary->id : null;

        return [
            ...$this->contentRules(),
            'status' => ['sometimes', 'required', Rule::enum(ItineraryStatus::class)],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('itineraries', 'slug')->ignore($itineraryId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->restoreEmptyStrings();

        if ($this->exists('slug') && $this->input('slug') === '') {
            $this->merge(['slug' => null]);
        }
    }
}
