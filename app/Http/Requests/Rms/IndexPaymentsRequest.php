<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPaymentsRequest extends FormRequest
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
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'booking_id' => ['sometimes', 'integer', 'exists:bookings,id'],
            'kind' => ['sometimes', Rule::enum(PaymentKind::class)],
            'method' => ['sometimes', Rule::enum(PaymentMethod::class)],
            'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'q' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
