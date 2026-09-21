<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Document;
use App\Models\User;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     booking_id: int,
     *     kind: string,
     *     kind_label: string,
     *     number: string|null,
     *     version: int,
     *     reason: string|null,
     *     payment_id: int|null,
     *     issued_at: string,
     *     file_sha256: string,
     *     issued_by: array{id: int, name: string}|null
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('issuedBy');

        $issuer = $this->issuedBy;

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'number' => $this->number,
            'version' => $this->version,
            'reason' => $this->reason,
            'payment_id' => $this->payment_id,
            'issued_at' => Iso::utc($this->issued_at),
            'file_sha256' => $this->file_sha256,
            'issued_by' => $issuer instanceof User ? [
                'id' => $issuer->id,
                'name' => $issuer->name,
            ] : null,
        ];
    }
}
