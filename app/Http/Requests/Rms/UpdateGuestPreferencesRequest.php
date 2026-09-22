<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Models\GuestPreference;
use App\Support\GuestExperience\PreferenceAnswerValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateGuestPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record', GuestPreference::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $raw = $this->input('answers');

            if (! is_array($raw)) {
                return;
            }

            try {
                $this->attributes->set('preference_answers', PreferenceAnswerValidator::validate($raw));
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function answers(): array
    {
        /** @var array<string, string> $answers */
        $answers = $this->attributes->get('preference_answers', []);

        return $answers;
    }
}
