<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\PaymentMethod;
use App\Models\RefundRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteRefundRequest extends FormRequest
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
        $refund = $this->route('refund');
        $amount = ['sometimes', 'integer'];

        if ($refund instanceof RefundRequest) {
            $amount[] = Rule::in([$refund->refund_due]);
        }

        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount' => $amount,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.in' => 'The amount must equal the refund due.',
        ];
    }
}
