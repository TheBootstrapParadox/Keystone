<?php

namespace BSPDX\Keystone\Http\Controllers;

use App\Models\User;
use BSPDX\Keystone\Exceptions\CannotDeleteSuperAdminRole;
use BSPDX\Keystone\Http\Support\PrivilegeEscalationGuard;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use BSPDX\Keystone\Support\DefaultGuard;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

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
    public function roles(Request $request): JsonResponse
    {
        $callerTenant = $this->callerTenantId($request);

        // One count query for every role; a tenant caller only counts members
        // of their own tenant so other tenants' membership is not disclosed.
        $roles = EloquentCollection::make($this->roleService->getAllWithPermissions()->all())
            ->loadCount(['users' => function ($query) use ($callerTenant) {
                if ($callerTenant !== null) {
                    $query->where($query->getModel()->qualifyColumn('tenant_id'), $callerTenant);
                }
            }]);

        return response()->json(['roles' => $roles->map(fn (KeystoneRole $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => $role->getAttribute('users_count'),
            'created_at' => $role->created_at?->toDateTimeString(),
        ])]);
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
                'created_at' => $permission->created_at?->toDateTimeString(),
            ];
        });

        return response()->json(['permissions' => $permissions]);
    }

    /**
     * Create a new role in the caller's tenant (global for a caller with no tenant).
     */
    public function createRole(Request $request): JsonResponse
    {
        $tenantId = $this->callerTenantId($request);

        $nameRules = ['required', 'string', $this->uniqueNameRule('roles', $request, $tenantId)];
        if ($tenantId !== null) {
            $nameRules[] = Rule::notIn([config('keystone.rbac.super_admin_role', 'super-admin')]);
        }

        $validated = $request->validate([
            'name' => $nameRules,
            'guard_name' => ['nullable', 'string', Rule::in(array_keys(config('auth.guards', [])))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', $this->resolvableRule(KeystonePermission::class, $tenantId)],
        ], [
            'name.not_in' => 'The :attribute is reserved for the global super-admin role.',
        ]);

        $permissions = $this->resolveAll(KeystonePermission::class, $validated['permissions'] ?? [], $tenantId);
        $this->escalationGuard($request)->grantPermissions($permissions, collect());

        $role = $this->roleService->create($validated['name'], $validated['guard_name'] ?? null);

        if ($permissions->isNotEmpty()) {
            $role = $this->roleService->syncPermissions($role, $permissions->all());
        }

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => $role->load('permissions'),
        ], 201);
    }

    /**
     * Create a new permission in the caller's tenant (global for a caller with no tenant).
     */
    public function createPermission(Request $request): JsonResponse
    {
        $tenantId = $this->callerTenantId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', $this->uniqueNameRule('permissions', $request, $tenantId)],
            'guard_name' => ['nullable', 'string', Rule::in(array_keys(config('auth.guards', [])))],
        ]);

        $permission = $this->permissionService->create(
            $validated['name'],
            $validated['guard_name'] ?? null
        );

        return response()->json([
            'message' => 'Permission created successfully.',
            'permission' => $permission,
        ], 201);
    }

    /**
     * Add roles to a user, keeping the roles they already hold.
     */
    public function assignRoles(Request $request, Model|string $user): JsonResponse
    {
        $user = $this->resolveUser($user);
        $tenantId = $this->tenantOf($user);

        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', $this->resolvableRule(KeystoneRole::class, $tenantId)],
        ]);

        $roles = $this->resolveAll(KeystoneRole::class, $validated['roles'], $tenantId);
        $this->escalationGuard($request)->grantRoles($roles, $user->roles()->get());

        $this->authorizationService->assignRolesToUser($user, $roles->all());

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
    public function syncRoles(Request $request, Model|string $user): JsonResponse
    {
        $user = $this->resolveUser($user);
        $tenantId = $this->tenantOf($user);

        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', $this->resolvableRule(KeystoneRole::class, $tenantId)],
        ]);

        $roles = $this->resolveAll(KeystoneRole::class, $validated['roles'], $tenantId);
        $this->escalationGuard($request)->syncRoles($roles, $user->roles()->get());

        $this->authorizationService->syncRolesForUser($user, $roles->all());

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
    public function assignPermissions(Request $request, Model|string $user): JsonResponse
    {
        $user = $this->resolveUser($user);
        $tenantId = $this->tenantOf($user);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', $this->resolvableRule(KeystonePermission::class, $tenantId)],
        ]);

        $permissions = $this->resolveAll(KeystonePermission::class, $validated['permissions'], $tenantId);
        $this->escalationGuard($request)->grantPermissions($permissions, $user->permissions()->get());

        $this->authorizationService->assignPermissionsToUser($user, $permissions->all());

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
    public function syncPermissions(Request $request, Model|string $user): JsonResponse
    {
        $user = $this->resolveUser($user);
        $tenantId = $this->tenantOf($user);

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', $this->resolvableRule(KeystonePermission::class, $tenantId)],
        ]);

        $permissions = $this->resolveAll(KeystonePermission::class, $validated['permissions'], $tenantId);
        $this->escalationGuard($request)->grantPermissions($permissions, $user->permissions()->get());

        $this->authorizationService->syncPermissionsForUser($user, $permissions->all());

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
        $this->denyTenantWriteToGlobal($request, $role);
        $this->escalationGuard($request)->modifyRole($role);
        $tenantId = $this->tenantOf($role);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', $this->resolvableRule(KeystonePermission::class, $tenantId)],
        ]);

        $permissions = $this->resolveAll(KeystonePermission::class, $validated['permissions'], $tenantId);
        $this->escalationGuard($request)->grantPermissions($permissions, $role->permissions()->get());

        $this->permissionService->assignToRole($role, $permissions->all());

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
        $this->denyTenantWriteToGlobal($request, $role);
        $this->escalationGuard($request)->modifyRole($role);
        $tenantId = $this->tenantOf($role);

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', $this->resolvableRule(KeystonePermission::class, $tenantId)],
        ]);

        $permissions = $this->resolveAll(KeystonePermission::class, $validated['permissions'], $tenantId);
        $this->escalationGuard($request)->grantPermissions($permissions, $role->permissions()->get());

        $role = $this->roleService->syncPermissions($role, $permissions->all());

        return response()->json([
            'message' => 'Role permissions synced successfully.',
            'role' => $role,
        ]);
    }

    /**
     * Get user's roles and permissions.
     */
    public function userRolesPermissions(Model|string $user): JsonResponse
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
    public function deleteRole(Request $request, KeystoneRole $role): JsonResponse
    {
        $this->denyTenantWriteToGlobal($request, $role);

        try {
            $this->roleService->delete($role);
        } catch (CannotDeleteSuperAdminRole $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * Remove a permission.
     */
    public function deletePermission(Request $request, KeystonePermission $permission): JsonResponse
    {
        $this->denyTenantWriteToGlobal($request, $permission);

        $this->permissionService->delete($permission);

        return response()->json([
            'message' => 'Permission deleted successfully.',
        ]);
    }

    /**
     * The caller's tenant in multi-tenant mode (null when tenant-less or single-tenant).
     */
    private function callerTenantId(Request $request): ?string
    {
        return config('keystone.features.multi_tenant', false)
            ? $request->user()?->getAttribute('tenant_id')
            : null;
    }

    /**
     * The tenant a model belongs to, as the reference for resolving names.
     */
    private function tenantOf(Model $model): ?string
    {
        return config('keystone.features.multi_tenant', false) ? $model->getAttribute('tenant_id') : null;
    }

    /**
     * Global roles and permissions are shared by every tenant, so a tenant
     * caller may read them but never change or delete them.
     */
    private function denyTenantWriteToGlobal(Request $request, KeystoneRole|KeystonePermission $model): void
    {
        if ($this->callerTenantId($request) !== null && $model->tenant_id === null) {
            abort(403, 'Global roles and permissions cannot be modified by a tenant.');
        }
    }

    private function escalationGuard(Request $request): PrivilegeEscalationGuard
    {
        return new PrivilegeEscalationGuard($request->user());
    }

    /**
     * Unique name among rows with the same guard in the given tenant.
     */
    private function uniqueNameRule(string $table, Request $request, ?string $tenantId): Unique
    {
        $guard = DefaultGuard::resolve($request->input('guard_name'));

        return Rule::unique($table, 'name')->where(function ($query) use ($guard, $tenantId) {
            $query->where('guard_name', $guard);

            if (config('keystone.features.multi_tenant', false)) {
                $tenantId === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', $tenantId);
            }
        });
    }

    /**
     * Validation rule: the name must resolve in the given tenant.
     *
     * @param  class-string<KeystoneRole|KeystonePermission>  $model
     */
    private function resolvableRule(string $model, ?string $tenantId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($model, $tenantId) {
            if (! is_string($value)) {
                return;
            }

            try {
                $model::findByNameForTenant($value, $tenantId);
            } catch (ModelNotFoundException) {
                $fail('The selected :attribute is invalid.');
            }
        };
    }

    /**
     * Resolve validated names to models in the given tenant.
     *
     * @template TModel of KeystoneRole|KeystonePermission
     *
     * @param  class-string<TModel>  $model
     * @param  array<int, string>  $names
     * @return Collection<int, TModel>
     */
    private function resolveAll(string $model, array $names, ?string $tenantId): Collection
    {
        return collect($names)
            ->unique()
            ->map(fn (string $name) => $model::findByNameForTenant($name, $tenantId))
            ->values();
    }

    /**
     * Resolve the {user} route segment to the configured user model.
     *
     * Resolved here rather than via a global Route::bind('user') so the
     * consuming app's own {user} bindings are left alone. If the app does bind
     * {user} (Route::model / Route::bind), the bound model arrives here and is
     * used as-is. Users in another tenant are reported as missing (404)
     * rather than forbidden, however they were resolved.
     */
    private function resolveUser(Model|string $user): Model&Authenticatable
    {
        $userModel = config('keystone.user.model')
            ?? config('auth.providers.users.model', User::class);

        if (! $user instanceof $userModel) {
            $user = $userModel::findOrFail($user instanceof Model ? $user->getKey() : $user);
        }

        $callerTenant = auth()->user()?->tenant_id;

        if (config('keystone.features.multi_tenant', false)
            && $callerTenant !== null
            && $user->tenant_id !== $callerTenant) {
            abort(404);
        }

        return $user;
    }
}
