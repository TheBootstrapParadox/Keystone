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

    /**
     * Find a permission by name.
     *
     * @param string $name
     * @param string $guardName
     * @param string|null $tenantId
     * @return KeystonePermission|null
     */
    public function findByName(string $name, string $guardName = 'web', ?string $tenantId = null): ?KeystonePermission
    {
        $query = KeystonePermission::where('name', $name)->where('guard_name', $guardName);

        if (config('keystone.features.multi_tenant', false) && $tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->first();
    }

    /**
     * Get all permissions for a specific tenant.
     *
     * @param string|null $tenantId
     * @return Collection
     */
    public function getAllForTenant(?string $tenantId = null): Collection
    {
        if (!config('keystone.features.multi_tenant', false)) {
            return $this->getAllWithRoles();
        }

        $query = KeystonePermission::with('roles');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Assign permission(s) directly to a user.
     *
     * @param Authenticatable $user
     * @param string|array $permissions
     * @return void
     */
    public function assignToUser(Authenticatable $user, string|array $permissions): void
    {
        $user->givePermissionTo($permissions);
    }

    /**
     * Remove permission(s) from a user.
     *
     * @param Authenticatable $user
     * @param string|array $permissions
     * @return void
     */
    public function removeFromUser(Authenticatable $user, string|array $permissions): void
    {
        $user->revokePermissionTo($permissions);
    }

    /**
     * Assign permission(s) to a role.
     *
     * @param \BSPDX\Keystone\Models\KeystoneRole $role
     * @param string|array $permissions
     * @return void
     */
    public function assignToRole(\BSPDX\Keystone\Models\KeystoneRole $role, string|array $permissions): void
    {
        $role->givePermissionTo($permissions);
    }

    /**
     * Remove permission(s) from a role.
     *
     * @param \BSPDX\Keystone\Models\KeystoneRole $role
     * @param string|array $permissions
     * @return void
     */
    public function removeFromRole(\BSPDX\Keystone\Models\KeystoneRole $role, string|array $permissions): void
    {
        $role->revokePermissionTo($permissions);
    }
}
