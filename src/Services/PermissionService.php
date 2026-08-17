<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class PermissionService implements PermissionServiceInterface
{
    /**
     * Get all permissions with their roles.
     */
    public function getAllWithRoles(): Collection
    {
        return KeystonePermission::with('roles')->get();
    }

    /**
     * Create a new permission.
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
     */
    public function delete(KeystonePermission $permission): void
    {
        $permission->delete();
    }

    /**
     * Sync permissions directly to a user.
     */
    public function syncToUser(Authenticatable $user, array $permissions): void
    {
        $user->syncPermissions($permissions);
    }

    /**
     * Get direct permissions assigned to a user.
     */
    public function getUserPermissions(Authenticatable $user): Collection
    {
        return $user->permissions;
    }

    /**
     * Get all permissions for a user (including via roles).
     */
    public function getAllUserPermissions(Authenticatable $user): Collection
    {
        return $user->getAllPermissions();
    }

    /**
     * Find a permission by name.
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
     */
    public function getAllForTenant(?string $tenantId = null): Collection
    {
        if (! config('keystone.features.multi_tenant', false)) {
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
     */
    public function assignToUser(Authenticatable $user, string|array $permissions): void
    {
        $user->givePermissionTo($permissions);
    }

    /**
     * Remove permission(s) from a user.
     */
    public function removeFromUser(Authenticatable $user, string|array $permissions): void
    {
        $user->revokePermissionTo($permissions);
    }

    /**
     * Assign permission(s) to a role.
     */
    public function assignToRole(KeystoneRole $role, string|array $permissions): void
    {
        $role->givePermissionTo($permissions);
    }

    /**
     * Remove permission(s) from a role.
     */
    public function removeFromRole(KeystoneRole $role, string|array $permissions): void
    {
        $role->revokePermissionTo($permissions);
    }
}
