<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     status: string,
     *     role: array{id: int, name: string, slug: string},
     *     flags: list<string>,
     *     last_login_at: string|null,
     *     invited_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('role');

        $role = $this->role;

        if (! $role instanceof Role) {
            throw new LogicException('Users must have a role.');
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status->value,
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ],
            'flags' => $role->flagValues(),
            'last_login_at' => self::utc($this->last_login_at),
            'invited_at' => self::utc($this->invited_at),
        ];
    }

    private static function utc(?Carbon $value): ?string
    {
        return $value?->clone()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
