<?php

declare(strict_types=1);

namespace App\Http\Resources\Portal;

use App\Models\AgencyUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgencyUser
 */
class PortalAgencyMeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     agency: array{name: string, reference: string, commission_pct: int, payment_terms: string, status: string},
     *     user: array{id: int, name: string, email: string},
     *     materials_exist: bool
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('agency');

        $agency = $this->agency;

        return [
            'agency' => [
                'name' => $agency->name,
                'reference' => $agency->reference,
                'commission_pct' => $agency->commission_pct,
                'payment_terms' => $agency->payment_terms,
                'status' => $agency->status->value,
            ],
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
            ],
            'materials_exist' => false,
        ];
    }
}
