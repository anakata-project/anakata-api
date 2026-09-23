<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Enums\PaymentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePortalPaymentLinkRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(PaymentKind::class), Rule::in([
                PaymentKind::Deposit->value,
                PaymentKind::Balance->value,
            ])],
        ];
    }
}
