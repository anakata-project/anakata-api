<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

use App\Enums\PreferenceQuestionType;

final readonly class PreferenceQuestion
{
    /**
     * @param  list<string>  $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public PreferenceQuestionType $type,
        public array $options,
        public bool $restricted,
        public bool $required = false,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: PreferenceQuestionType,
     *     options: list<string>,
     *     restricted: bool,
     *     required: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $this->options,
            'restricted' => $this->restricted,
            'required' => $this->required,
        ];
    }
}
