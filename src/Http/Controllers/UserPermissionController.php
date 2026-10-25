<?php

namespace BSPDX\Keystone\Http\Controllers;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Http\Controllers\Concerns\ResolvesManagementContext;
use BSPDX\Keystone\Http\Support\TenantAwareNames;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPermissionController
{
    use ResolvesManagementContext;

    public function __construct(
        private PermissionServiceInterface $permissionService,
        private AuthorizationServiceInterface $authorizationService
    ) {}

    /**
     * Add direct permissions to a user, keeping the ones they already hold.
     */
    public function store(Request $request, Model|string $user): JsonResponse
    {
        $user = $this->resolveUser($request, $user);
        $permissionNames = TenantAwareNames::permissions($this->tenantOf($user));

        $validated = $request->validate([
            'permissions' => ['required', 'array', 'max:100'],
            'permissions.*' => ['string', 'distinct', $permissionNames->rule()],
        ]);

        $permissions = $permissionNames->resolve($validated['permissions']);
        $this->escalationGuard($request)->grantPermissions($permissions, $user->permissions()->get());

        $this->authorizationService->assignPermissionsToUser($user, $permissions->all());

        return $this->respond($user, 'Permissions assigned successfully.');
    }

    /**
     * Replace a user's direct permissions with exactly the given set.
     */
    public function update(Request $request, Model|string $user): JsonResponse
    {
        $user = $this->resolveUser($request, $user);
        $permissionNames = TenantAwareNames::permissions($this->tenantOf($user));

        $validated = $request->validate([
            'permissions' => ['present', 'array', 'max:100'],
            'permissions.*' => ['string', 'distinct', $permissionNames->rule()],
        ]);

        $permissions = $permissionNames->resolve($validated['permissions']);
        $this->escalationGuard($request)->grantPermissions($permissions, $user->permissions()->get());

        $this->authorizationService->syncPermissionsForUser($user, $permissions->all());

        return $this->respond($user, 'Permissions synced successfully.');
    }

    private function respond(KeystoneUser&Model $user, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
                'permissions' => $this->permissionService->getAllUserPermissions($user)->pluck('name'),
            ],
        ]);
    }
}
