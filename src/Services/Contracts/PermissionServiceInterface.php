<?php

namespace BSPDX\Keystone\Services\Contracts;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PermissionServiceInterface
{
    /**
     * Get all permissions with their roles, ordered by name then id.
     */
    public function getAllWithRoles(): Collection;

    /**
     * One page of the permissions getAllWithRoles() returns.
     *
     * @return LengthAwarePaginator<int, KeystonePermission>
     */
    public function paginateWithRoles(int $perPage, ?int $page = null): LengthAwarePaginator;

    /**
     * Create a new permission.
     */
    public function create(string $name, ?string $guardName = null): KeystonePermission;

    /**
     * Delete a permission.
     */
    public function delete(KeystonePermission $permission): void;

    /**
     * Sync permissions directly to a user.
     */
    public function syncToUser(KeystoneUser $user, array $permissions): void;

    /**
     * Get direct permissions assigned to a user.
     */
    public function getUserPermissions(KeystoneUser $user): Collection;

    /**
     * Get all permissions for a user (including via roles).
     */
    public function getAllUserPermissions(KeystoneUser $user): Collection;

    /**
     * Find a permission by name.
     */
    public function findByName(string $name, ?string $guardName = null, ?string $tenantId = null): ?KeystonePermission;

    /**
     * Get all permissions for a specific tenant.
     */
    public function getAllForTenant(?string $tenantId = null): Collection;

    /**
     * Assign permission(s) directly to a user.
     */
    public function assignToUser(KeystoneUser $user, string|array $permissions): void;

    /**
     * Remove permission(s) from a user.
     */
    public function removeFromUser(KeystoneUser $user, string|array $permissions): void;

    /**
     * Assign permission(s) to a role.
     */
    public function assignToRole(KeystoneRole $role, string|array $permissions): void;

    /**
     * Remove permission(s) from a role.
     */
    public function removeFromRole(KeystoneRole $role, string|array $permissions): void;
}
