<?php

namespace BSPDX\Keystone\Services\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AuthorizationServiceInterface
{
    /**
     * Assign roles to a user.
     */
    public function assignRolesToUser(Authenticatable $user, array $roles): void;

    /**
     * Assign permissions directly to a user.
     */
    public function assignPermissionsToUser(Authenticatable $user, array $permissions): void;

    /**
     * Check if user has a role.
     */
    public function userHasRole(Authenticatable $user, string|array $roles): bool;

    /**
     * Check if user has a permission.
     */
    public function userHasPermission(Authenticatable $user, string|array $permissions): bool;

    /**
     * Check if user can bypass all permission checks (super admin).
     */
    public function userCanBypassPermissions(Authenticatable $user): bool;

    /**
     * Check if user has any of the given roles.
     */
    public function userHasAnyRole(Authenticatable $user, string|array $roles): bool;

    /**
     * Check if user has all of the given roles.
     */
    public function userHasAllRoles(Authenticatable $user, string|array $roles): bool;

    /**
     * Check if user has any of the given permissions.
     */
    public function userHasAnyPermission(Authenticatable $user, string|array $permissions): bool;

    /**
     * Check if user has all of the given permissions.
     */
    public function userHasAllPermissions(Authenticatable $user, string|array $permissions): bool;

    /**
     * Check if user has a direct permission (not via role).
     */
    public function userHasDirectPermission(Authenticatable $user, string $permission): bool;
}
