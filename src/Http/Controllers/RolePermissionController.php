<?php

namespace BSPDX\Keystone\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use App\Models\User;

class RolePermissionController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private RoleServiceInterface $roleService,
        private PermissionServiceInterface $permissionService,
        private AuthorizationServiceInterface $authorizationService
    ) {}
    /**
     * Get all roles.
     */
    public function roles(): JsonResponse
    {
        $roles = $this->roleService->getAllWithPermissions()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => $role->users()->count(),
                'created_at' => $role->created_at->toDateTimeString(),
            ];
        });

        return response()->json(['roles' => $roles]);
    }

    /**
     * Get all permissions.
     */
    public function permissions(): JsonResponse
    {
        $permissions = $this->permissionService->getAllWithRoles()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'roles' => $permission->roles->pluck('name'),
                'created_at' => $permission->created_at->toDateTimeString(),
            ];
        });

        return response()->json(['permissions' => $permissions]);
    }

    /**
     * Create a new role.
     */
    public function createRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:roles,name'],
            'guard_name' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = $this->roleService->create(
            $validated['name'],
            $validated['guard_name'] ?? 'web'
        );

        if (isset($validated['permissions'])) {
            $role = $this->roleService->syncPermissions($role, $validated['permissions']);
        }

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => $role->load('permissions'),
        ], 201);
    }

    /**
     * Create a new permission.
     */
    public function createPermission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'unique:permissions,name'],
            'guard_name' => ['nullable', 'string'],
        ]);

        $permission = $this->permissionService->create(
            $validated['name'],
            $validated['guard_name'] ?? 'web'
        );

        return response()->json([
            'message' => 'Permission created successfully.',
            'permission' => $permission,
        ], 201);
    }

    /**
     * Assign roles to a user.
     */
    public function assignRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $this->authorizationService->assignRolesToUser($user, $validated['roles']);

        return response()->json([
            'message' => 'Roles assigned successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'roles' => $user->roles->pluck('name'),
            ],
        ]);
    }

    /**
     * Assign permissions to a user.
     */
    public function assignPermissions(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->authorizationService->assignPermissionsToUser($user, $validated['permissions']);

        return response()->json([
            'message' => 'Permissions assigned successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'permissions' => $this->permissionService->getAllUserPermissions($user)->pluck('name'),
            ],
        ]);
    }

    /**
     * Assign permissions to a role.
     */
    public function assignPermissionsToRole(Request $request, KeystoneRole $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = $this->roleService->syncPermissions($role, $validated['permissions']);

        return response()->json([
            'message' => 'Permissions assigned to role successfully.',
            'role' => $role,
        ]);
    }

    /**
     * Get user's roles and permissions.
     */
    public function userRolesPermissions(User $user): JsonResponse
    {
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]),
                'permissions' => $this->permissionService->getAllUserPermissions($user)->map(fn($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                ]),
                'direct_permissions' => $this->permissionService->getUserPermissions($user)->pluck('name'),
            ],
        ]);
    }

    /**
     * Remove a role.
     */
    public function deleteRole(KeystoneRole $role): JsonResponse
    {
        try {
            $this->roleService->delete($role);

            return response()->json([
                'message' => 'Role deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Remove a permission.
     */
    public function deletePermission(KeystonePermission $permission): JsonResponse
    {
        $this->permissionService->delete($permission);

        return response()->json([
            'message' => 'Permission deleted successfully.',
        ]);
    }
}
