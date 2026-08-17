<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class RoleService implements RoleServiceInterface
{
    /**
     * The cache service instance.
     */
    protected CacheServiceInterface $cacheService;

    /**
     * Create a new role service instance.
     */
    public function __construct(CacheServiceInterface $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Get all roles with their permissions.
     */
    public function getAllWithPermissions(): Collection
    {
        return KeystoneRole::with('permissions')->get();
    }

    /**
     * Create a new role.
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
     */
    public function syncPermissions(KeystoneRole $role, array $permissions): KeystoneRole
    {
        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }

    /**
     * Get all roles for a user.
     */
    public function getUserRoles(Authenticatable $user): Collection
    {
        return $user->roles;
    }

    /**
     * Find a role by name.
     */
    public function findByName(string $name, string $guardName = 'web', ?string $tenantId = null): ?KeystoneRole
    {
        $query = KeystoneRole::where('name', $name)->where('guard_name', $guardName);

        if (config('keystone.features.multi_tenant', false) && $tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->first();
    }

    /**
     * Get all roles for a specific tenant.
     */
    public function getAllForTenant(?string $tenantId = null): Collection
    {
        if (! config('keystone.features.multi_tenant', false)) {
            return $this->getAllWithPermissions();
        }

        $query = KeystoneRole::with('permissions');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Assign role(s) to a user.
     */
    public function assignToUser(Authenticatable $user, string|array $roles): void
    {
        $user->assignRole($roles);
    }

    /**
     * Remove role(s) from a user.
     */
    public function removeFromUser(Authenticatable $user, string|array $roles): void
    {
        $user->removeRole($roles);
    }

    /**
     * Clear the role and permission cache.
     */
    public function clearCache(): void
    {
        $this->cacheService->clearPermissionCache();
    }
}
