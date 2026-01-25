<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthorizationService implements AuthorizationServiceInterface
{
    /**
     * Assign roles to a user.
     *
     * @param Authenticatable $user
     * @param array $roles
     * @return void
     */
    public function assignRolesToUser(Authenticatable $user, array $roles): void
    {
        $user->syncRoles($roles);
    }

    /**
     * Assign permissions directly to a user.
     *
     * @param Authenticatable $user
     * @param array $permissions
     * @return void
     */
    public function assignPermissionsToUser(Authenticatable $user, array $permissions): void
    {
        $user->syncPermissions($permissions);
    }

    /**
     * Check if user has a role.
     *
     * @param Authenticatable $user
     * @param string|array $roles
     * @return bool
     */
    public function userHasRole(Authenticatable $user, string|array $roles): bool
    {
        return $user->hasAnyRole($roles);
    }

    /**
     * Check if user has a permission.
     *
     * @param Authenticatable $user
     * @param string|array $permissions
     * @return bool
     */
    public function userHasPermission(Authenticatable $user, string|array $permissions): bool
    {
        return $user->hasAnyPermission($permissions);
    }

    /**
     * Check if user can bypass all permission checks (super admin).
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function userCanBypassPermissions(Authenticatable $user): bool
    {
        return $user->canBypassPermissions();
    }
}
