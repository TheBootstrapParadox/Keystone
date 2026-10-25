<?php

namespace BSPDX\Keystone\Services\Contracts;

use BSPDX\Keystone\Contracts\KeystoneUser;

interface AuthorizationServiceInterface
{
    /**
     * Add roles to a user, keeping the roles they already hold.
     */
    public function assignRolesToUser(KeystoneUser $user, array $roles): void;

    /**
     * Add direct permissions to a user, keeping the ones they already hold.
     */
    public function assignPermissionsToUser(KeystoneUser $user, array $permissions): void;

    /**
     * Replace a user's roles with exactly the given set (empty clears them).
     */
    public function syncRolesForUser(KeystoneUser $user, array $roles): void;

    /**
     * Replace a user's direct permissions with exactly the given set (empty clears them).
     */
    public function syncPermissionsForUser(KeystoneUser $user, array $permissions): void;

    /**
     * Check if user has a role.
     */
    public function userHasRole(KeystoneUser $user, string|array $roles): bool;

    /**
     * Check if user has a permission.
     */
    public function userHasPermission(KeystoneUser $user, string|array $permissions): bool;

    /**
     * Check if user can bypass all permission checks (super admin).
     */
    public function userCanBypassPermissions(KeystoneUser $user): bool;

    /**
     * Check if user has any of the given roles.
     */
    public function userHasAnyRole(KeystoneUser $user, string|array $roles): bool;

    /**
     * Check if user has all of the given roles.
     */
    public function userHasAllRoles(KeystoneUser $user, string|array $roles): bool;

    /**
     * Check if user has any of the given permissions.
     */
    public function userHasAnyPermission(KeystoneUser $user, string|array $permissions): bool;

    /**
     * Check if user has all of the given permissions.
     */
    public function userHasAllPermissions(KeystoneUser $user, string|array $permissions): bool;

    /**
     * Check if user has a direct permission (not via role).
     */
    public function userHasDirectPermission(KeystoneUser $user, string $permission): bool;
}
