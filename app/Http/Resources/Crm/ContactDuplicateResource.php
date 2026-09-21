<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Contact $a
 * @property Contact $b
 * @property list<string> $reasons
 */
class ContactDuplicateResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{a: array<string, mixed>, b: array<string, mixed>, reasons: list<string>}
     */
    public function toArray(Request $request): array
    {
        /** @var array{a: Contact, b: Contact, reasons: list<string>} $pair */
        $pair = $this->resource;

        return [
            'a' => (new ContactResource($pair['a']))->toArray($request),
            'b' => (new ContactResource($pair['b']))->toArray($request),
            'reasons' => $pair['reasons'],
        ];
    }
}
