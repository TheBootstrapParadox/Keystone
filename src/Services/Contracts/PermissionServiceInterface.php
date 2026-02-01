<?php

namespace BSPDX\Keystone\Services\Contracts;

use BSPDX\Keystone\Models\KeystonePermission;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Authenticatable;

interface PermissionServiceInterface
{
    /**
     * Get all permissions with their roles.
     *
     * @return Collection
     */
    public function getAllWithRoles(): Collection;

    /**
     * Create a new permission.
     *
     * @param string $name
     * @param string $guardName
     * @return KeystonePermission
     */
    public function create(string $name, string $guardName = 'web'): KeystonePermission;

    /**
     * Delete a permission.
     *
     * @param KeystonePermission $permission
     * @return void
     */
    public function delete(KeystonePermission $permission): void;

    /**
     * Sync permissions directly to a user.
     *
     * @param Authenticatable $user
     * @param array $permissions
     * @return void
     */
    public function syncToUser(Authenticatable $user, array $permissions): void;

    /**
     * Get direct permissions assigned to a user.
     *
     * @param Authenticatable $user
     * @return Collection
     */
    public function getUserPermissions(Authenticatable $user): Collection;

    /**
     * Get all permissions for a user (including via roles).
     *
     * @param Authenticatable $user
     * @return Collection
     */
    public function getAllUserPermissions(Authenticatable $user): Collection;

    /**
     * Find a permission by name.
     *
     * @param string $name
     * @param string $guardName
     * @param string|null $tenantId
     * @return KeystonePermission|null
     */
    public function findByName(string $name, string $guardName = 'web', ?string $tenantId = null): ?KeystonePermission;

    /**
     * Get all permissions for a specific tenant.
     *
     * @param string|null $tenantId
     * @return Collection
     */
    public function getAllForTenant(?string $tenantId = null): Collection;

    /**
     * Assign permission(s) directly to a user.
     *
     * @param Authenticatable $user
     * @param string|array $permissions
     * @return void
     */
    public function assignToUser(Authenticatable $user, string|array $permissions): void;

    /**
     * Remove permission(s) from a user.
     *
     * @param Authenticatable $user
     * @param string|array $permissions
     * @return void
     */
    public function removeFromUser(Authenticatable $user, string|array $permissions): void;

    /**
     * Assign permission(s) to a role.
     *
     * @param \BSPDX\Keystone\Models\KeystoneRole $role
     * @param string|array $permissions
     * @return void
     */
    public function assignToRole(\BSPDX\Keystone\Models\KeystoneRole $role, string|array $permissions): void;

    /**
     * Remove permission(s) from a role.
     *
     * @param \BSPDX\Keystone\Models\KeystoneRole $role
     * @param string|array $permissions
     * @return void
     */
    public function removeFromRole(\BSPDX\Keystone\Models\KeystoneRole $role, string|array $permissions): void;
}
