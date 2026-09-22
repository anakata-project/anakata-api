<?php

declare(strict_types=1);

namespace App\Http\Requests\Privacy;

use Illuminate\Foundation\Http\FormRequest;

class EraseSubjectRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'verified_how' => ['required', 'string', 'max:2000'],
            'confirmation' => ['required', 'string', 'max:255'],
        ];
    }
}
