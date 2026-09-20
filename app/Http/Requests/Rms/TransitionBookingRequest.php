<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\BookingStatus;
use App\Support\Bookings\Transitions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason') && is_string($this->input('reason'))) {
            $this->merge(['reason' => trim((string) $this->input('reason'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', Rule::enum(BookingStatus::class)],
            'reason' => [
                Rule::requiredIf(function (): bool {
                    $to = BookingStatus::tryFrom((string) $this->input('to'));

                    return $to instanceof BookingStatus && Transitions::reasonRequired($to);
                }),
                'nullable',
                'string',
            ],
        ];
    }
}
