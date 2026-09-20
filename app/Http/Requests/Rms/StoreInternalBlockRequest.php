<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\BlockReason;
use App\Support\Blocks\ScopeSummary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInternalBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(BlockReason::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'departures' => ['required', 'array', 'min:1', 'max:20'],
            'departures.*.departure_id' => ['required', 'integer', 'distinct', 'exists:departures,id'],
            'departures.*.cabin_codes' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $after): void {
            $departures = $this->input('departures');

            if (! is_array($departures)) {
                return;
            }

            foreach ($departures as $index => $row) {
                if (! is_array($row) || ! array_key_exists('cabin_codes', $row)) {
                    continue;
                }

                $codes = $row['cabin_codes'];

                if ($codes === 'ALL') {
                    continue;
                }

                if (! is_array($codes) || $codes === []) {
                    $after->errors()->add(
                        "departures.{$index}.cabin_codes",
                        'Cabin codes must be ALL or a list of S1–S8 / OWNER.',
                    );

                    continue;
                }

                foreach ($codes as $code) {
                    if (! is_string($code) || ! in_array($code, ScopeSummary::ALL_CABIN_CODES, true)) {
                        $after->errors()->add(
                            "departures.{$index}.cabin_codes",
                            'Cabin codes must be S1–S8, OWNER, or ALL.',
                        );

                        break;
                    }
                }
            }
        });
    }
}
