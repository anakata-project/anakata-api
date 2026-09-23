<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\SalesMaterialKind;
use App\Rules\SalesMaterialUpload;
use App\Support\SalesMaterials\MaterialFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');

        if (is_string($title)) {
            $this->merge(['title' => trim($title)]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(SalesMaterialKind::class)],
            'agency_id' => ['sometimes', 'nullable', 'integer', 'exists:agencies,id'],
            'file' => ['required', 'file', 'max:'.MaterialFile::MAX_KILOBYTES, new SalesMaterialUpload],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'The file must not be larger than 50 MB.',
        ];
    }
}
