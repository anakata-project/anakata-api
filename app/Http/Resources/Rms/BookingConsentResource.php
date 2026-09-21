<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Consent;
use App\Support\Consents\BookingConsentRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingConsentRow
 */
class BookingConsentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     document: string,
     *     label: string,
     *     required: bool,
     *     current_version: string,
     *     outdated: bool,
     *     consent: ConsentResource|null
     * }
     */
    public function toArray(Request $request): array
    {
        $document = $this->document;
        $consent = $this->consent;
        $current = $this->versions->for($document);
        $outdated = $consent instanceof Consent && $consent->version !== $current;

        return [
            'document' => $document->value,
            'label' => $document->label(),
            'required' => $document->required(),
            'current_version' => $current,
            // @var bool
            'outdated' => $outdated,
            'consent' => $consent instanceof Consent
                ? new ConsentResource($consent)
                : null,
        ];
    }
}
