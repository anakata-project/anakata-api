<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

use Illuminate\Validation\Validator;

final readonly class SurveyAnswers
{
    public function __construct(
        public int $score,
        public ?int $recommend,
        public ?string $why,
        public ?string $best,
        public ?string $better,
        public ?string $crew,
        public ?string $callNotes,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function from(array $input): self
    {
        return new self(
            score: (int) $input['score'],
            recommend: self::score($input['rec'] ?? null),
            why: self::text($input['why'] ?? null),
            best: self::text($input['best'] ?? null),
            better: self::text($input['better'] ?? null),
            crew: self::text($input['crew'] ?? null),
            callNotes: self::text($input['call_notes'] ?? null),
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(bool $callNotes): array
    {
        return SurveyQuestions::rules($callNotes);
    }

    /**
     * @param  array<mixed>  $input
     */
    public static function rejectUnknown(array $input, bool $callNotes, Validator $validator): void
    {
        foreach (array_keys($input) as $key) {
            if (! is_string($key) || SurveyQuestions::allows($key, $callNotes)) {
                continue;
            }

            $validator->errors()->add($key, 'This question is not on the survey.');
        }
    }

    private static function score(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
