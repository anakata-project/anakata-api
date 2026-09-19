<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

final class BusinessRulesConstraint implements ValidationRule, ValidatorAwareRule
{
    private ?Validator $validator = null;

    public function __construct(private readonly string $check) {}

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $data = $this->validator?->getData() ?? [];

        match ($this->check) {
            'default_lte_cap' => $this->defaultLteCap($value, $data, $fail),
            'reminders_decreasing' => $this->remindersDecreasing($value, $fail),
            'bands' => $this->bands($value, $fail),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function defaultLteCap(mixed $value, array $data, Closure $fail): void
    {
        $cap = data_get($data, 'commission.cap_pct');

        if (! is_numeric($value) || ! is_numeric($cap)) {
            return;
        }

        $default = (int) $value;
        $capPct = (int) $cap;

        if ($default > $capPct) {
            $fail('Default agency commission ('.$default.'%) is above the cap ('.$capPct.'%).');
        }
    }

    private function remindersDecreasing(mixed $value, Closure $fail): void
    {
        if (! is_array($value) || count($value) !== 2) {
            return;
        }

        if (! is_numeric($value[0] ?? null) || ! is_numeric($value[1] ?? null)) {
            return;
        }

        if ((int) $value[1] >= (int) $value[0]) {
            $fail('Second balance reminder must be closer to the due date than the first.');
        }
    }

    private function bands(mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $mins = [];

        foreach ($value as $band) {
            if (! is_array($band) || ! is_numeric($band['min_days'] ?? null)) {
                continue;
            }

            $mins[] = (int) $band['min_days'];
        }

        if ($mins === []) {
            return;
        }

        if (count(array_unique($mins)) !== count($mins)) {
            $fail('Two cancellation bands start on the same day.');
        }

        if (! in_array(0, $mins, true)) {
            $fail('The last cancellation band must start at 0 days.');
        }
    }
}
