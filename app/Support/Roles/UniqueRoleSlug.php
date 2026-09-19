<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Enums\SystemRole;
use App\Models\Role;
use Illuminate\Support\Str;

final class UniqueRoleSlug
{
    public static function fromName(string $name): string
    {
        $base = Str::slug($name);

        if (self::available($base)) {
            return $base;
        }

        $suffix = 2;

        while (true) {
            $candidate = $base === '' ? '-'.$suffix : $base.'-'.$suffix;

            if (self::available($candidate)) {
                return $candidate;
            }

            $suffix++;
        }
    }

    private static function available(string $slug): bool
    {
        if ($slug === '' || SystemRole::tryFrom($slug) instanceof SystemRole) {
            return false;
        }

        return ! Role::query()->where('slug', $slug)->exists();
    }
}
