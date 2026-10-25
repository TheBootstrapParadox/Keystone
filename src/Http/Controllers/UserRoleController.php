<?php

namespace BSPDX\Keystone\Http\Controllers;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Http\Controllers\Concerns\ResolvesManagementContext;
use BSPDX\Keystone\Http\Support\TenantAwareNames;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController
{
    use ResolvesManagementContext;

    public function __construct(
        private RoleServiceInterface $roleService,
        private AuthorizationServiceInterface $authorizationService
    ) {}

    /**
     * Add roles to a user, keeping the roles they already hold.
     */
    public function store(Request $request, Model|string $user): JsonResponse
    {
        $user = $this->resolveUser($request, $user);
        $roleNames = TenantAwareNames::roles($this->tenantOf($user));

        $validated = $request->validate([
            'roles' => ['required', 'array', 'max:100'],
            'roles.*' => ['string', 'distinct', $roleNames->rule()],
        ]);

        $roles = $roleNames->resolve($validated['roles']);
        $this->escalationGuard($request)->grantRoles($roles, $user->roles()->get());

        $this->authorizationService->assignRolesToUser($user, $roles->all());

        return $this->respond($user, 'Roles assigned successfully.');
    }

    /**
     * Replace a user's roles with exactly the given set.
     */
    public function update(Request $request, Model|string $user): JsonResponse
    {
        $user = $this->resolveUser($request, $user);
        $roleNames = TenantAwareNames::roles($this->tenantOf($user));

        $validated = $request->validate([
            'roles' => ['present', 'array', 'max:100'],
            'roles.*' => ['string', 'distinct', $roleNames->rule()],
        ]);

        $roles = $roleNames->resolve($validated['roles']);
        $this->escalationGuard($request)->syncRoles($roles, $user->roles()->get());

        $this->authorizationService->syncRolesForUser($user, $roles->all());

        return $this->respond($user, 'Roles synced successfully.');
    }

    private function respond(KeystoneUser&Model $user, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
                'roles' => $this->roleService->getUserRoles($user)->pluck('name'),
            ],
        ]);
    }
}
