<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{value: string|null, on_file: bool} $resource
 */
class MaskedNoteResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{value: string|null, on_file: bool}
     */
    public function toArray(Request $request): array
    {
        /** @var array{value: string|null, on_file: bool} $note */
        $note = $this->resource;

        return [
            // @var string|null
            'value' => $note['value'],
            // @var bool
            'on_file' => $note['on_file'],
        ];
    }
}
