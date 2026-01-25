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
}
