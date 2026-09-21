<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\CharterEnquiryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCharterEnquiriesRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(CharterEnquiryStatus::class)],
        ];
    }
}
