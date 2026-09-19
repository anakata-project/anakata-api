<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\ConfigVersion;
use App\Models\User;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConfigVersion
 */
class ConfigCurrentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     version: int,
     *     document: array<string, mixed>,
     *     published_at: string,
     *     published_by: array{id: int, name: string}|null,
     *     approval_reference: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $publisher = $this->publisher;

        return [
            'version' => $this->version,
            'document' => $this->asDocument()->toArray(),
            'published_at' => Iso::utc($this->published_at),
            'published_by' => $publisher instanceof User
                ? ['id' => $publisher->id, 'name' => $publisher->name]
                : null,
            'approval_reference' => $this->approval_reference,
        ];
    }
}
