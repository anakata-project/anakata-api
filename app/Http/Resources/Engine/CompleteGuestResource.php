<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Guest
 */
class CompleteGuestResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     booking_id: int,
     *     first_name: string,
     *     last_name: string,
     *     dob: string|null,
     *     nationality: string|null,
     *     ecuador_resident: bool,
     *     passport_on_file: bool,
     *     passport_expiry: string|null,
     *     email: string|null,
     *     insurance_declared: bool,
     *     is_minor_now: bool,
     *     guardian: array{name: string|null, relationship: string|null, consented: bool}|null
     * }
     */
    public function toArray(Request $request): array
    {
        $minor = $this->isMinorNow();

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'dob' => $this->dob?->toDateString(),
            'nationality' => $this->nationality,
            'ecuador_resident' => $this->ecuador_resident,
            'passport_on_file' => $this->passport_no !== null && $this->passport_no !== '',
            'passport_expiry' => $this->passport_expiry?->toDateString(),
            'email' => $this->email,
            'insurance_declared' => $this->insurance_declared,
            'is_minor_now' => $minor,
            'guardian' => $minor ? [
                'name' => $this->guardian_name,
                'relationship' => $this->guardian_relationship,
                'consented' => $this->guardian_consented_at !== null,
            ] : null,
        ];
    }
}
