<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

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
            recommend: self::score($input['recommend'] ?? null),
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
        $rules = [
            'score' => ['required', 'integer', 'min:1', 'max:10'],
            'recommend' => ['nullable', 'integer', 'min:0', 'max:10'],
            'why' => ['nullable', 'string', 'max:1000'],
            'best' => ['nullable', 'string', 'max:1000'],
            'better' => ['nullable', 'string', 'max:1000'],
            'crew' => ['nullable', 'string', 'max:1000'],
        ];

        if ($callNotes) {
            $rules['call_notes'] = ['nullable', 'string', 'max:1000'];
            $rules['guest_id'] = ['required', 'integer'];
        }

        return $rules;
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
