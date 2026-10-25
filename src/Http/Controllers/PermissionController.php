<?php

namespace BSPDX\Keystone\Http\Controllers;

use BSPDX\Keystone\Http\Controllers\Concerns\ResolvesManagementContext;
use BSPDX\Keystone\Http\Resources\PermissionResource;
use BSPDX\Keystone\Http\Support\TenantAwareNames;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController
{
    use ResolvesManagementContext;

    public function __construct(private PermissionServiceInterface $permissionService) {}

    /**
     * List permissions with the names of the roles holding them, ordered by
     * name; one page of them when per_page is given.
     */
    public function index(Request $request): JsonResponse
    {
        $pagination = $this->validatePagination($request);

        if (! isset($pagination['per_page'])) {
            return response()->json([
                'permissions' => PermissionResource::collection($this->permissionService->getAllWithRoles())->resolve($request),
            ]);
        }

        $paginator = $this->permissionService->paginateWithRoles(
            (int) $pagination['per_page'],
            isset($pagination['page']) ? (int) $pagination['page'] : null
        );

        return response()->json([
            'permissions' => PermissionResource::collection($paginator->items())->resolve($request),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    /**
     * Create a permission in the caller's tenant (global for a caller with no tenant).
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->callerTenantId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', TenantAwareNames::uniqueRule('permissions', $this->requestedGuard($request), $tenantId)],
            'guard_name' => ['nullable', 'string', Rule::in(array_keys(config('auth.guards', [])))],
        ]);

        $permission = $this->permissionService->create(
            $validated['name'],
            $validated['guard_name'] ?? null
        );

        return response()->json([
            'message' => 'Permission created successfully.',
            'permission' => PermissionResource::make($permission)->resolve($request),
        ], 201);
    }

    /**
     * Delete a permission.
     */
    public function destroy(Request $request, KeystonePermission $permission): JsonResponse
    {
        $this->denyTenantWriteToGlobal($request, $permission);

        $this->permissionService->delete($permission);

        return response()->json([
            'message' => 'Permission deleted successfully.',
        ]);
    }
}
