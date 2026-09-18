<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\Permission;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * @implements CastsAttributes<Collection<int, Permission>, iterable<int, Permission|string>|null>
 */
final class PermissionCollection implements CastsAttributes
{
    /** @var array<string, true> */
    private static array $loggedUnknowns = [];

    public static function resetLoggedUnknowns(): void
    {
        self::$loggedUnknowns = [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, Permission>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Collection
    {
        $raw = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($raw)) {
            return collect();
        }

        /** @var Collection<int, Permission> $permissions */
        $permissions = collect();

        foreach ($raw as $item) {
            $permission = $this->toPermission($item, $model);

            if ($permission instanceof Permission) {
                $permissions->push($permission);
            }
        }

        return $permissions->unique()->values();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $values = collect($value ?? [])
            ->map(fn (mixed $item): ?string => $this->toPermission($item, $model)?->value)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [$key => json_encode(array_values($values))];
    }

    private function toPermission(mixed $item, Model $model): ?Permission
    {
        if ($item instanceof Permission) {
            return $item;
        }

        if (! is_string($item)) {
            return null;
        }

        $permission = Permission::tryFrom($item);

        if ($permission instanceof Permission) {
            return $permission;
        }

        $this->logUnknown($item, $model);

        return null;
    }

    private function logUnknown(string $value, Model $model): void
    {
        if (isset(self::$loggedUnknowns[$value])) {
            return;
        }

        self::$loggedUnknowns[$value] = true;

        Log::warning('Unknown permission dropped from role', [
            'permission' => $value,
            'role_id' => $model->getKey(),
        ]);
    }
}
