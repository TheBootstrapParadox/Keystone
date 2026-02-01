<?php

namespace BSPDX\Keystone\Services\Contracts;

use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Authenticatable;

interface RoleServiceInterface
{
    /**
     * Get all roles with their permissions.
     *
     * @return Collection
     */
    public function getAllWithPermissions(): Collection;

    /**
     * Create a new role.
     *
     * @param string $name
     * @param string $guardName
     * @return KeystoneRole
     */
    public function create(string $name, string $guardName = 'web'): KeystoneRole;

    /**
     * Delete a role.
     *
     * @param KeystoneRole $role
     * @return void
     * @throws \Exception if role cannot be deleted
     */
    public function delete(KeystoneRole $role): void;

    /**
     * Sync permissions to a role.
     *
     * @param KeystoneRole $role
     * @param array $permissions
     * @return KeystoneRole
     */
    public function syncPermissions(KeystoneRole $role, array $permissions): KeystoneRole;

    /**
     * Get all roles for a user.
     *
     * @param Authenticatable $user
     * @return Collection
     */
    public function getUserRoles(Authenticatable $user): Collection;

    /**
     * Find a role by name.
     *
     * @param string $name
     * @param string $guardName
     * @param string|null $tenantId
     * @return KeystoneRole|null
     */
    public function findByName(string $name, string $guardName = 'web', ?string $tenantId = null): ?KeystoneRole;

    /**
     * Get all roles for a specific tenant.
     *
     * @param string|null $tenantId
     * @return Collection
     */
    public function getAllForTenant(?string $tenantId = null): Collection;

    /**
     * Assign role(s) to a user.
     *
     * @param Authenticatable $user
     * @param string|array $roles
     * @return void
     */
    public function assignToUser(Authenticatable $user, string|array $roles): void;

    /**
     * Remove role(s) from a user.
     *
     * @param Authenticatable $user
     * @param string|array $roles
     * @return void
     */
    public function removeFromUser(Authenticatable $user, string|array $roles): void;

    /**
     * Clear the role and permission cache.
     *
     * @return void
     */
    public function clearCache(): void;
}
