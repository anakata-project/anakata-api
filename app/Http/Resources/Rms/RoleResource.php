<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     description: string|null,
     *     is_system: bool,
     *     is_admin: bool,
     *     users_count: int,
     *     permissions: list<string>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'is_admin' => $this->isAdmin(),
            'users_count' => (int) ($this->users_count ?? $this->users()->count()),
            'permissions' => $this->permissionValues(),
        ];
    }
}
