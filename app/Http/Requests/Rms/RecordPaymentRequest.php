<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordPaymentRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(PaymentKind::class), Rule::notIn([PaymentKind::Refund->value])],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'note' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('status')) {
                return;
            }

            if ($this->input('method') !== PaymentMethod::Wire->value) {
                $validator->errors()->add('status', 'An explicit status is only accepted for a wire.');
            }
        });
    }
}
