<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\SalesMaterials\MaterialFile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class SalesMaterialUpload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The file must be a PDF, PNG, JPG, MP4, or ZIP.');

            return;
        }

        $size = $value->getSize();

        if (is_int($size) && $size > MaterialFile::MAX_KILOBYTES * 1024) {
            return;
        }

        $mime = MaterialFile::detect($value);

        if ($mime === null || ! array_key_exists($mime, MaterialFile::EXTENSIONS)) {
            $fail('The file must be a PDF, PNG, JPG, MP4, or ZIP.');
        }
    }
}
