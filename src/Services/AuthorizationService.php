<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;

class AuthorizationService implements AuthorizationServiceInterface
{
    /**
     * Add roles to a user, keeping the roles they already hold.
     */
    public function assignRolesToUser(KeystoneUser $user, array $roles): void
    {
        $user->assignRole($roles);
    }

    /**
     * Add direct permissions to a user, keeping the ones they already hold.
     */
    public function assignPermissionsToUser(KeystoneUser $user, array $permissions): void
    {
        $user->givePermissionTo($permissions);
    }

    /**
     * Replace a user's roles with exactly the given set (empty clears them).
     */
    public function syncRolesForUser(KeystoneUser $user, array $roles): void
    {
        $user->syncRoles($roles);
    }

    /**
     * Replace a user's direct permissions with exactly the given set (empty clears them).
     */
    public function syncPermissionsForUser(KeystoneUser $user, array $permissions): void
    {
        $user->syncPermissions($permissions);
    }

    /**
     * Check if user has a role.
     */
    public function userHasRole(KeystoneUser $user, string|array $roles): bool
    {
        // Check for super admin bypass (the trait's role checks are literal)
        if ($this->userCanBypassPermissions($user)) {
            return true;
        }

        return $user->hasAnyRole($roles);
    }

    /**
     * Check if user has a permission.
     */
    public function userHasPermission(KeystoneUser $user, string|array $permissions): bool
    {
        return $user->hasAnyPermission($permissions);
    }

    /**
     * Check if user can bypass all permission checks (super admin).
     */
    public function userCanBypassPermissions(KeystoneUser $user): bool
    {
        return $user->canBypassPermissions();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function userHasAnyRole(KeystoneUser $user, string|array $roles): bool
    {
        // Check for super admin bypass
        if ($this->userCanBypassPermissions($user)) {
            return true;
        }

        return $user->hasAnyRole($roles);
    }

    /**
     * Check if user has all of the given roles.
     */
    public function userHasAllRoles(KeystoneUser $user, string|array $roles): bool
    {
        // Check for super admin bypass
        if ($this->userCanBypassPermissions($user)) {
            return true;
        }

        return $user->hasAllRoles($roles);
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function userHasAnyPermission(KeystoneUser $user, string|array $permissions): bool
    {
        // Check for super admin bypass
        if ($this->userCanBypassPermissions($user)) {
            return true;
        }

        return $user->hasAnyPermission($permissions);
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function userHasAllPermissions(KeystoneUser $user, string|array $permissions): bool
    {
        // Check for super admin bypass
        if ($this->userCanBypassPermissions($user)) {
            return true;
        }

        return $user->hasAllPermissions($permissions);
    }

    /**
     * Check if user has a direct permission (not via role).
     */
    public function userHasDirectPermission(KeystoneUser $user, string $permission): bool
    {
        // Check for super admin bypass
        if ($this->userCanBypassPermissions($user)) {
            return true;
        }

        return $user->hasDirectPermission($permission);
    }
}
