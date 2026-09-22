<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Models\GuestResponse;
use Illuminate\Foundation\Http\FormRequest;

class NpsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', GuestResponse::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
