<?php

namespace BSPDX\Keystone\Services\Contracts;

use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface RoleServiceInterface
{
    /**
     * Get all roles with their permissions.
     */
    public function getAllWithPermissions(): Collection;

    /**
     * Create a new role.
     */
    public function create(string $name, string $guardName = 'web'): KeystoneRole;

    /**
     * Delete a role.
     *
     * @throws \Exception if role cannot be deleted
     */
    public function delete(KeystoneRole $role): void;

    /**
     * Sync permissions to a role.
     */
    public function syncPermissions(KeystoneRole $role, array $permissions): KeystoneRole;

    /**
     * Get all roles for a user.
     */
    public function getUserRoles(Authenticatable $user): Collection;

    /**
     * Find a role by name.
     */
    public function findByName(string $name, string $guardName = 'web', ?string $tenantId = null): ?KeystoneRole;

    /**
     * Get all roles for a specific tenant.
     */
    public function getAllForTenant(?string $tenantId = null): Collection;

    /**
     * Assign role(s) to a user.
     */
    public function assignToUser(Authenticatable $user, string|array $roles): void;

    /**
     * Remove role(s) from a user.
     */
    public function removeFromUser(Authenticatable $user, string|array $roles): void;

    /**
     * Clear the role and permission cache.
     */
    public function clearCache(): void;
}
