<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Rms\PermissionResource;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PermissionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        return PermissionResource::collection(Permission::cases());
    }
}
