<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthorizationService implements AuthorizationServiceInterface
{
    /**
     * Add roles to a user, keeping the roles they already hold.
     */
    public function assignRolesToUser(Authenticatable $user, array $roles): void
    {
        $user->assignRole($roles);
    }

    /**
     * Add direct permissions to a user, keeping the ones they already hold.
     */
    public function assignPermissionsToUser(Authenticatable $user, array $permissions): void
    {
        $user->givePermissionTo($permissions);
    }

    /**
     * Replace a user's roles with exactly the given set (empty clears them).
     */
    public function syncRolesForUser(Authenticatable $user, array $roles): void
    {
        $user->syncRoles($roles);
    }

    /**
     * Replace a user's direct permissions with exactly the given set (empty clears them).
     */
    public function syncPermissionsForUser(Authenticatable $user, array $permissions): void
    {
        $user->syncPermissions($permissions);
    }

    /**
     * Check if user has a role.
     */
    public function userHasRole(Authenticatable $user, string|array $roles): bool
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
    public function userHasPermission(Authenticatable $user, string|array $permissions): bool
    {
        return $user->hasAnyPermission($permissions);
    }

    /**
     * Check if user can bypass all permission checks (super admin).
     */
    public function userCanBypassPermissions(Authenticatable $user): bool
    {
        return $user->canBypassPermissions();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function userHasAnyRole(Authenticatable $user, string|array $roles): bool
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
    public function userHasAllRoles(Authenticatable $user, string|array $roles): bool
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
    public function userHasAnyPermission(Authenticatable $user, string|array $permissions): bool
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
    public function userHasAllPermissions(Authenticatable $user, string|array $permissions): bool
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
    public function userHasDirectPermission(Authenticatable $user, string $permission): bool
    {
        // Check for super admin bypass
        if ($this->userCanBypassPermissions($user)) {
            return true;
        }

        return $user->hasDirectPermission($permission);
    }
}
