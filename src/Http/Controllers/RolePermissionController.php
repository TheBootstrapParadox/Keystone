<?php

namespace BSPDX\Keystone\Http\Controllers;

use BSPDX\Keystone\Http\Controllers\Concerns\ResolvesManagementContext;
use BSPDX\Keystone\Http\Resources\RoleResource;
use BSPDX\Keystone\Http\Support\TenantAwareNames;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A role's permissions.
 */
class RolePermissionController
{
    use ResolvesManagementContext;

    public function __construct(
        private RoleServiceInterface $roleService,
        private PermissionServiceInterface $permissionService
    ) {}

    /**
     * Add permissions to a role, keeping the ones it already holds.
     */
    public function store(Request $request, KeystoneRole $role): JsonResponse
    {
        $this->denyTenantWriteToGlobal($request, $role);
        $this->escalationGuard($request)->modifyRole($role);
        $permissionNames = TenantAwareNames::permissions($this->tenantOf($role), $role->guard_name);

        $validated = $request->validate([
            'permissions' => ['required', 'array', 'max:100'],
            'permissions.*' => ['string', 'distinct', $permissionNames->rule()],
        ]);

        $permissions = $permissionNames->resolve($validated['permissions']);
        $this->escalationGuard($request)->grantPermissions($permissions, $role->permissions()->get());

        $this->permissionService->assignToRole($role, $permissions->all());

        return response()->json([
            'message' => 'Permissions assigned to role successfully.',
            'role' => RoleResource::make($role->load('permissions'))->resolve($request),
        ]);
    }

    /**
     * Replace a role's permissions with exactly the given set.
     */
    public function update(Request $request, KeystoneRole $role): JsonResponse
    {
        $this->denyTenantWriteToGlobal($request, $role);
        $this->escalationGuard($request)->modifyRole($role);
        $permissionNames = TenantAwareNames::permissions($this->tenantOf($role), $role->guard_name);

        $validated = $request->validate([
            'permissions' => ['present', 'array', 'max:100'],
            'permissions.*' => ['string', 'distinct', $permissionNames->rule()],
        ]);

        $permissions = $permissionNames->resolve($validated['permissions']);
        $this->escalationGuard($request)->grantPermissions($permissions, $role->permissions()->get());

        $role = $this->roleService->syncPermissions($role, $permissions->all());

        return response()->json([
            'message' => 'Role permissions synced successfully.',
            'role' => RoleResource::make($role)->resolve($request),
        ]);
    }
}
