<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\Users\DisableUser;
use App\Actions\Users\EnableUser;
use App\Actions\Users\InviteUser;
use App\Actions\Users\ResendInvitation;
use App\Actions\Users\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\DisableUserRequest;
use App\Http\Requests\Rms\IndexUsersRequest;
use App\Http\Requests\Rms\InviteUserRequest;
use App\Http\Requests\Rms\UpdateUserRequest;
use App\Http\Resources\Rms\ChangeHistoryResource;
use App\Http\Resources\Rms\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class UserController extends Controller
{
    public function index(IndexUsersRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $perPage = $request->integer('per_page', 25);
        $status = $request->validated('status');
        $roleId = $request->validated('role_id');
        $search = $request->validated('q');

        $users = User::query()
            ->with('role')
            ->when(is_string($status), fn (Builder $query) => $query->where('status', $status))
            ->when($roleId !== null, fn (Builder $query) => $query->where('role_id', $roleId))
            ->when(is_string($search) && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate($perPage);

        return UserResource::collection($users);
    }

    public function store(InviteUserRequest $request, InviteUser $action): JsonResponse
    {
        $role = Role::query()->findOrFail((int) $request->validated('role_id'));
        $this->authorize('create', [User::class, $role]);

        $user = $action->handle(
            (string) $request->validated('name'),
            (string) $request->validated('email'),
            $role,
        );

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $action): UserResource
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $newRole = null;

        if ($request->exists('role_id')) {
            $newRole = Role::query()->findOrFail((int) $request->validated('role_id'));
        }

        $this->authorize('update', [$user, $newRole]);

        $name = $request->exists('name') ? (string) $request->validated('name') : null;

        return new UserResource($action->handle($actor, $user, $name, $newRole)->load('role'));
    }

    public function disable(DisableUserRequest $request, User $user, DisableUser $action): UserResource
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $this->authorize('disable', $user);

        $reason = $request->validated('reason');

        return new UserResource($action->handle(
            $actor,
            $user,
            is_string($reason) ? $reason : null,
        )->load('role'));
    }

    public function enable(User $user, EnableUser $action): UserResource
    {
        $this->authorize('enable', $user);

        return new UserResource($action->handle($user)->load('role'));
    }

    public function resendInvitation(User $user, ResendInvitation $action): UserResource
    {
        $this->authorize('resendInvitation', $user);

        return new UserResource($action->handle($user)->load('role'));
    }

    public function history(User $user): AnonymousResourceCollection
    {
        $this->authorize('viewHistory', $user);

        $entries = $user->history()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return ChangeHistoryResource::collection($entries);
    }
}
