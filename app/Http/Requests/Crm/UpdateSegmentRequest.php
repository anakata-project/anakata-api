<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\SegmentDimension;
use App\Enums\SegmentKind;
use App\Support\Crm\SegmentVocabulary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateSegmentRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:160'],
            'sentence' => ['sometimes', 'string', 'max:500'],
            'conditions' => ['sometimes', 'array'],
            'dimensions' => ['sometimes', 'array'],
            'dimensions.*.axis' => ['required_with:dimensions', Rule::enum(SegmentDimension::class)],
            'dimensions.*.label' => ['required_with:dimensions', 'string', 'max:160'],
            'kind' => ['sometimes', Rule::enum(SegmentKind::class)],
            'feeds' => ['sometimes', 'string', 'max:500'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->exists('conditions')) {
                return;
            }

            $conditions = $this->input('conditions');

            if (! is_array($conditions)) {
                return;
            }

            try {
                SegmentVocabulary::assertValid($conditions);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }
}
