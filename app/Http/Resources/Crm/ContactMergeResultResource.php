<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\Contact;
use App\Models\ContactMerge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ContactMerge $merge
 * @property Contact $contact
 * @property bool $swapped
 */
class ContactMergeResultResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{swapped: bool, merge: ContactMergeResource, contact: ContactResource}
     */
    public function toArray(Request $request): array
    {
        /** @var array{merge: ContactMerge, contact: Contact, swapped: bool} $result */
        $result = $this->resource;

        return [
            'swapped' => $result['swapped'],
            'merge' => new ContactMergeResource($result['merge']),
            'contact' => new ContactResource($result['contact']),
        ];
    }
}
