<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

use App\Enums\SurveyQuestionType;

final readonly class SurveyQuestion
{
    public function __construct(
        public string $key,
        public string $label,
        public SurveyQuestionType $type,
        public ?int $min,
        public ?int $max,
        public bool $required,
        public ?int $textMax,
    ) {}

    /**
     * @return array{key: string, label: string, type: SurveyQuestionType, min: int|null, max: int|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'min' => $this->min,
            'max' => $this->max,
        ];
    }
}
