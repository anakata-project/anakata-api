<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Roles\CreateRole;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\UpdateRole;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\StoreRoleRequest;
use App\Http\Requests\Rms\UpdateRoleRequest;
use App\Http\Resources\Rms\ChangeHistoryResource;
use App\Http\Resources\Rms\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

final class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount('users')
            ->orderByRaw('CASE slug WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', [
                SystemRole::Admin->value,
                SystemRole::Manager->value,
                SystemRole::SalesExec->value,
            ])
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request, CreateRole $action): JsonResponse
    {
        $permissions = $this->permissionsFrom($request->validated('permissions') ?? []);
        $this->authorize('create', [Role::class, $permissions]);

        $description = $request->validated('description');

        $role = $action->handle(
            (string) $request->validated('name'),
            is_string($description) ? $description : null,
            $permissions,
        );

        return (new RoleResource($role))->response()->setStatusCode(201);
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRole $action): RoleResource
    {
        $validated = $request->validated();
        $added = collect();

        if (array_key_exists('permissions', $validated)) {
            $incoming = $this->permissionsFrom($validated['permissions']);
            $added = $incoming
                ->filter(fn (Permission $permission): bool => ! $role->permissions->contains($permission))
                ->values();
            $validated['permissions'] = $incoming;
        }

        $this->authorize('update', [$role, $added]);

        return new RoleResource($action->handle($role, $validated));
    }

    public function destroy(Role $role, DeleteRole $action): Response
    {
        $this->authorize('delete', $role);

        $action->handle($role);

        return response()->noContent();
    }

    public function history(Role $role): AnonymousResourceCollection
    {
        $this->authorize('viewHistory', $role);

        $entries = $role->history()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return ChangeHistoryResource::collection($entries);
    }

    /**
     * @param  list<mixed>  $values
     * @return Collection<int, Permission>
     */
    private function permissionsFrom(array $values): Collection
    {
        /** @var Collection<int, Permission> $permissions */
        $permissions = collect($values)
            ->map(fn (mixed $value): Permission => $value instanceof Permission
                ? $value
                : Permission::from((string) $value))
            ->values();

        return $permissions;
    }
}
