<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Permission
 */
class PermissionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     value: string,
     *     label: string,
     *     group: string,
     *     group_label: string,
     *     is_flag: bool
     * }
     */
    public function toArray(Request $request): array
    {
        $permission = $this->resource;

        if (! $permission instanceof Permission) {
            throw new \LogicException('PermissionResource must wrap a Permission enum.');
        }

        return [
            'value' => $permission->value,
            'label' => $permission->label(),
            'group' => $permission->group(),
            'group_label' => Permission::groupLabel($permission->group()),
            'is_flag' => $permission->isFlag(),
        ];
    }
}
