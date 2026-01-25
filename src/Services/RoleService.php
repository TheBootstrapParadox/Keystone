<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Authenticatable;

class RoleService implements RoleServiceInterface
{
    /**
     * Get all roles with their permissions.
     *
     * @return Collection
     */
    public function getAllWithPermissions(): Collection
    {
        return KeystoneRole::with('permissions')->get();
    }

    /**
     * Create a new role.
     *
     * @param string $name
     * @param string $guardName
     * @return KeystoneRole
     */
    public function create(string $name, string $guardName = 'web'): KeystoneRole
    {
        return KeystoneRole::create([
            'name' => $name,
            'guard_name' => $guardName,
        ]);
    }

    /**
     * Delete a role.
     *
     * @param KeystoneRole $role
     * @return void
     * @throws \Exception if role cannot be deleted
     */
    public function delete(KeystoneRole $role): void
    {
        if ($role->isSuperAdmin()) {
            throw new \Exception('Cannot delete the super admin role.');
        }

        $role->delete();
    }

    /**
     * Sync permissions to a role.
     *
     * @param KeystoneRole $role
     * @param array $permissions
     * @return KeystoneRole
     */
    public function syncPermissions(KeystoneRole $role, array $permissions): KeystoneRole
    {
        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }

    /**
     * Get all roles for a user.
     *
     * @param Authenticatable $user
     * @return Collection
     */
    public function getUserRoles(Authenticatable $user): Collection
    {
        return $user->roles;
    }
}
