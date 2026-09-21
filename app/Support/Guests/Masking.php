<?php

declare(strict_types=1);

namespace App\Support\Guests;

final class Masking
{
    public static function passport(?string $value, bool $canViewSensitive): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($canViewSensitive) {
            return $value === '' ? null : $value;
        }

        if (strlen($value) < 6) {
            return '••••';
        }

        return '•••• '.substr($value, -3);
    }

    /**
     * @return array{value: string|null, on_file: bool}
     */
    public static function note(?string $value, bool $canViewSensitive): array
    {
        $onFile = $value !== null && $value !== '';

        if ($canViewSensitive) {
            return [
                'value' => $onFile ? $value : null,
                'on_file' => $onFile,
            ];
        }

        return [
            'value' => null,
            'on_file' => $onFile,
        ];
    }
}
