<?php

namespace BSPDX\Keystone\Services\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AuthorizationServiceInterface
{
    /**
     * Assign roles to a user.
     *
     * @param Authenticatable $user
     * @param array $roles
     * @return void
     */
    public function assignRolesToUser(Authenticatable $user, array $roles): void;

    /**
     * Assign permissions directly to a user.
     *
     * @param Authenticatable $user
     * @param array $permissions
     * @return void
     */
    public function assignPermissionsToUser(Authenticatable $user, array $permissions): void;

    /**
     * Check if user has a role.
     *
     * @param Authenticatable $user
     * @param string|array $roles
     * @return bool
     */
    public function userHasRole(Authenticatable $user, string|array $roles): bool;

    /**
     * Check if user has a permission.
     *
     * @param Authenticatable $user
     * @param string|array $permissions
     * @return bool
     */
    public function userHasPermission(Authenticatable $user, string|array $permissions): bool;

    /**
     * Check if user can bypass all permission checks (super admin).
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function userCanBypassPermissions(Authenticatable $user): bool;

    /**
     * Check if user has any of the given roles.
     *
     * @param Authenticatable $user
     * @param string|array $roles
     * @return bool
     */
    public function userHasAnyRole(Authenticatable $user, string|array $roles): bool;

    /**
     * Check if user has all of the given roles.
     *
     * @param Authenticatable $user
     * @param string|array $roles
     * @return bool
     */
    public function userHasAllRoles(Authenticatable $user, string|array $roles): bool;

    /**
     * Check if user has any of the given permissions.
     *
     * @param Authenticatable $user
     * @param string|array $permissions
     * @return bool
     */
    public function userHasAnyPermission(Authenticatable $user, string|array $permissions): bool;

    /**
     * Check if user has all of the given permissions.
     *
     * @param Authenticatable $user
     * @param string|array $permissions
     * @return bool
     */
    public function userHasAllPermissions(Authenticatable $user, string|array $permissions): bool;

    /**
     * Check if user has a direct permission (not via role).
     *
     * @param Authenticatable $user
     * @param string $permission
     * @return bool
     */
    public function userHasDirectPermission(Authenticatable $user, string $permission): bool;
}
