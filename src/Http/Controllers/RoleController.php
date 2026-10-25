<?php

namespace BSPDX\Keystone\Http\Controllers;

use BSPDX\Keystone\Http\Controllers\Concerns\ResolvesManagementContext;
use BSPDX\Keystone\Http\Resources\RoleResource;
use BSPDX\Keystone\Http\Support\TenantAwareNames;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController
{
    use ResolvesManagementContext;

    public function __construct(private RoleServiceInterface $roleService) {}

    /**
     * List roles with their permission names and member counts, ordered by
     * name; one page of them when per_page is given.
     */
    public function index(Request $request): JsonResponse
    {
        $pagination = $this->validatePagination($request);
        $perPage = isset($pagination['per_page']) ? (int) $pagination['per_page'] : null;

        $paginator = $perPage !== null
            ? $this->roleService->paginateWithPermissions($perPage, isset($pagination['page']) ? (int) $pagination['page'] : null)
            : null;
        $callerTenant = $this->callerTenantId($request);

        // One count query for the listed roles; a tenant caller only counts
        // members of their own tenant so other tenants' membership is not disclosed,
        // and only assignments those members can see (their tenant's or global),
        // as hasRole() does.
        $roles = EloquentCollection::make($paginator?->items() ?? $this->roleService->getAllWithPermissions()->all())
            ->loadCount(['users' => function ($query) use ($callerTenant) {
                if ($callerTenant !== null) {
                    $query->where($query->getModel()->qualifyColumn('tenant_id'), $callerTenant)
                        ->where(fn ($q) => $q->where('model_has_roles.tenant_id', $callerTenant)->orWhereNull('model_has_roles.tenant_id'));
                }
            }]);

        $response = ['roles' => RoleResource::collection($roles)->resolve($request)];

        if ($paginator !== null) {
            $response['meta'] = $this->paginationMeta($paginator);
        }

        return response()->json($response);
    }

    /**
     * Create a role in the caller's tenant (global for a caller with no
     * tenant), with its permissions, in one transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->callerTenantId($request);
        $guard = $this->requestedGuard($request);
        // Permission names resolve on the role's guard, not the default one
        $permissionNames = TenantAwareNames::permissions($tenantId, $guard);

        $nameRules = ['required', 'string', TenantAwareNames::uniqueRule('roles', $guard, $tenantId)];
        if ($tenantId !== null) {
            $nameRules[] = Rule::notIn([config('keystone.rbac.super_admin_role', 'super-admin')]);
        }

        $validated = $request->validate([
            'name' => $nameRules,
            'guard_name' => ['nullable', 'string', Rule::in(array_keys(config('auth.guards', [])))],
            'permissions' => ['nullable', 'array', 'max:100'],
            'permissions.*' => ['string', 'distinct', $permissionNames->rule()],
        ], [
            'name.not_in' => 'The :attribute is reserved for the global super-admin role.',
        ]);

        $permissions = $permissionNames->resolve($validated['permissions'] ?? []);
        $this->escalationGuard($request)->grantPermissions($permissions, collect());

        $role = DB::transaction(function () use ($validated, $permissions) {
            $role = $this->roleService->create($validated['name'], $validated['guard_name'] ?? null);

            return $permissions->isNotEmpty()
                ? $this->roleService->syncPermissions($role, $permissions->all())
                : $role;
        });

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => RoleResource::make($role->load('permissions'))->resolve($request),
        ], 201);
    }

    /**
     * Delete a role (never the super-admin role).
     */
    public function destroy(Request $request, KeystoneRole $role): JsonResponse
    {
        $this->denyTenantWriteToGlobal($request, $role);

        $this->roleService->delete($role);

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }
}
