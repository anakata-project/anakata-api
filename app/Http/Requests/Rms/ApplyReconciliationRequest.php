<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\PaymentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyReconciliationRequest extends FormRequest
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
            'stripe_id' => ['required', 'string'],
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'kind' => ['required', Rule::enum(PaymentKind::class), Rule::notIn([PaymentKind::Refund->value])],
        ];
    }
}
