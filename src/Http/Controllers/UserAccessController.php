<?php

namespace BSPDX\Keystone\Http\Controllers;

use BSPDX\Keystone\Http\Controllers\Concerns\ResolvesManagementContext;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything a user holds: roles, effective permissions, direct permissions.
 */
class UserAccessController
{
    use ResolvesManagementContext;

    public function __construct(
        private RoleServiceInterface $roleService,
        private PermissionServiceInterface $permissionService
    ) {}

    /**
     * Show a user's roles and permissions.
     */
    public function show(Request $request, Model|string $user): JsonResponse
    {
        $user = $this->resolveUser($request, $user);

        return response()->json([
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
                'email' => $user->getAttribute('email'),
                'roles' => $this->roleService->getUserRoles($user)->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]),
                'permissions' => $this->permissionService->getAllUserPermissions($user)->map(fn ($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                ]),
                'direct_permissions' => $this->permissionService->getUserPermissions($user)->pluck('name'),
            ],
        ]);
    }
}
