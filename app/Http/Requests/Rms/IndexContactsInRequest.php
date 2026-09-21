<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class IndexContactsInRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function fromDate(): ?string
    {
        $from = $this->validated('from');

        return is_string($from) && $from !== '' ? $from : null;
    }

    public function toDate(): ?string
    {
        $to = $this->validated('to');

        return is_string($to) && $to !== '' ? $to : null;
    }
}
