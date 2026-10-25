<?php

namespace BSPDX\Keystone\Services\Contracts;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Exceptions\CannotDeleteSuperAdminRole;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RoleServiceInterface
{
    /**
     * Get all roles with their permissions, ordered by name then id.
     */
    public function getAllWithPermissions(): Collection;

    /**
     * One page of the roles getAllWithPermissions() returns.
     *
     * @return LengthAwarePaginator<int, KeystoneRole>
     */
    public function paginateWithPermissions(int $perPage, ?int $page = null): LengthAwarePaginator;

    /**
     * Create a new role.
     */
    public function create(string $name, ?string $guardName = null): KeystoneRole;

    /**
     * Delete a role.
     *
     * @throws CannotDeleteSuperAdminRole
     */
    public function delete(KeystoneRole $role): void;

    /**
     * Sync permissions to a role.
     */
    public function syncPermissions(KeystoneRole $role, array $permissions): KeystoneRole;

    /**
     * Get all roles for a user.
     */
    public function getUserRoles(KeystoneUser $user): Collection;

    /**
     * Find a role by name.
     */
    public function findByName(string $name, ?string $guardName = null, ?string $tenantId = null): ?KeystoneRole;

    /**
     * Get all roles for a specific tenant.
     */
    public function getAllForTenant(?string $tenantId = null): Collection;

    /**
     * Assign role(s) to a user.
     */
    public function assignToUser(KeystoneUser $user, string|array $roles): void;

    /**
     * Remove role(s) from a user.
     */
    public function removeFromUser(KeystoneUser $user, string|array $roles): void;

    /**
     * Clear the role and permission cache.
     */
    public function clearCache(): void;
}
