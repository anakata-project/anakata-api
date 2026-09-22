<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

use App\Enums\PreferenceQuestionType;
use Illuminate\Validation\ValidationException;

final class PreferenceAnswerValidator
{
    /**
     * Keys that were sent, trimmed. An empty string was sent blank.
     * A key that was omitted is absent, which is not the same as blank for restricted answers.
     *
     * @param  array<mixed>  $answers
     * @return array<string, string>
     */
    public static function validate(array $answers): array
    {
        $errors = [];
        $clean = [];

        foreach ($answers as $key => $value) {
            $question = is_string($key) ? PreferenceQuestions::find($key) : null;

            if ($question === null) {
                $errors['answers.'.$key] = ['This question is not on the questionnaire.'];

                continue;
            }

            if ($value !== null && ! is_string($value)) {
                $errors['answers.'.$key] = ['The answer must be text.'];

                continue;
            }

            $text = trim((string) $value);

            if (mb_strlen($text) > 500) {
                $errors['answers.'.$key] = ['The answer may not be greater than 500 characters.'];

                continue;
            }

            if ($question->type === PreferenceQuestionType::Choice
                && $text !== ''
                && ! in_array($text, $question->options, true)
            ) {
                $errors['answers.'.$key] = ['Choose one of the listed options.'];

                continue;
            }

            $clean[$key] = $text;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }
}
