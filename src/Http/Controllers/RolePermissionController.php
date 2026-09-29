<?php

namespace BSPDX\Keystone\Http\Controllers;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Add roles to a user, keeping the roles they already hold.
     */
    public function assignRoles(Request $request, string $user): JsonResponse
    {
        $user = $this->resolveUser($user);

        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $this->authorizationService->assignRolesToUser($user, $validated['roles']);

        return response()->json([
            'message' => 'Roles assigned successfully.',
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
                'roles' => $this->roleService->getUserRoles($user)->pluck('name'),
            ],
        ]);
    }

    /**
     * Replace a user's roles with exactly the given set.
     */
    public function syncRoles(Request $request, string $user): JsonResponse
    {
        $user = $this->resolveUser($user);

        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $this->authorizationService->syncRolesForUser($user, $validated['roles']);

        return response()->json([
            'message' => 'Roles synced successfully.',
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
                'roles' => $this->roleService->getUserRoles($user)->pluck('name'),
            ],
        ]);
    }

    /**
     * Add direct permissions to a user, keeping the ones they already hold.
     */
    public function assignPermissions(Request $request, string $user): JsonResponse
    {
        $user = $this->resolveUser($user);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->authorizationService->assignPermissionsToUser($user, $validated['permissions']);

        return response()->json([
            'message' => 'Permissions assigned successfully.',
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
                'permissions' => $this->permissionService->getAllUserPermissions($user)->pluck('name'),
            ],
        ]);
    }

    /**
     * Replace a user's direct permissions with exactly the given set.
     */
    public function syncPermissions(Request $request, string $user): JsonResponse
    {
        $user = $this->resolveUser($user);

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->authorizationService->syncPermissionsForUser($user, $validated['permissions']);

        return response()->json([
            'message' => 'Permissions synced successfully.',
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
                'permissions' => $this->permissionService->getAllUserPermissions($user)->pluck('name'),
            ],
        ]);
    }

    /**
     * Add permissions to a role, keeping the ones it already holds.
     */
    public function assignPermissionsToRole(Request $request, KeystoneRole $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->permissionService->assignToRole($role, $validated['permissions']);

        return response()->json([
            'message' => 'Permissions assigned to role successfully.',
            'role' => $role->load('permissions'),
        ]);
    }

    /**
     * Replace a role's permissions with exactly the given set.
     */
    public function syncRolePermissions(Request $request, KeystoneRole $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = $this->roleService->syncPermissions($role, $validated['permissions']);

        return response()->json([
            'message' => 'Role permissions synced successfully.',
            'role' => $role,
        ]);
    }

    /**
     * Get user's roles and permissions.
     */
    public function userRolesPermissions(string $user): JsonResponse
    {
        $user = $this->resolveUser($user);

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

    /**
     * Resolve the {user} route segment to the configured user model.
     *
     * Resolved here rather than via a global Route::bind('user') so the
     * consuming app's own {user} bindings are left alone. Users in another
     * tenant are reported as missing (404) rather than forbidden.
     */
    private function resolveUser(string $id): Model&Authenticatable
    {
        $userModel = config('keystone.user.model')
            ?? config('auth.providers.users.model', User::class);

        $user = $userModel::findOrFail($id);

        $callerTenant = auth()->user()?->tenant_id;

        if (config('keystone.features.multi_tenant', false)
            && $callerTenant !== null
            && $user->tenant_id !== $callerTenant) {
            abort(404);
        }

        return $user;
    }
}
