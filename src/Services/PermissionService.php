<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Authenticatable;

class PermissionService implements PermissionServiceInterface
{
    /**
     * Get all permissions with their roles.
     *
     * @return Collection
     */
    public function getAllWithRoles(): Collection
    {
        return KeystonePermission::with('roles')->get();
    }

    /**
     * Create a new permission.
     *
     * @param string $name
     * @param string $guardName
     * @return KeystonePermission
     */
    public function create(string $name, string $guardName = 'web'): KeystonePermission
    {
        return KeystonePermission::create([
            'name' => $name,
            'guard_name' => $guardName,
        ]);
    }

    /**
     * Delete a permission.
     *
     * @param KeystonePermission $permission
     * @return void
     */
    public function delete(KeystonePermission $permission): void
    {
        $permission->delete();
    }

    /**
     * Sync permissions directly to a user.
     *
     * @param Authenticatable $user
     * @param array $permissions
     * @return void
     */
    public function syncToUser(Authenticatable $user, array $permissions): void
    {
        $user->syncPermissions($permissions);
    }

    /**
     * Get direct permissions assigned to a user.
     *
     * @param Authenticatable $user
     * @return Collection
     */
    public function getUserPermissions(Authenticatable $user): Collection
    {
        return $user->permissions;
    }

    /**
     * Get all permissions for a user (including via roles).
     *
     * @param Authenticatable $user
     * @return Collection
     */
    public function getAllUserPermissions(Authenticatable $user): Collection
    {
        return $user->getAllPermissions();
    }
}
