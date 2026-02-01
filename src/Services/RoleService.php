<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Authenticatable;

class RoleService implements RoleServiceInterface
{
    /**
     * The cache service instance.
     *
     * @var CacheServiceInterface
     */
    protected CacheServiceInterface $cacheService;

    /**
     * Create a new role service instance.
     *
     * @param CacheServiceInterface $cacheService
     */
    public function __construct(CacheServiceInterface $cacheService)
    {
        $this->cacheService = $cacheService;
    }

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

    /**
     * Find a role by name.
     *
     * @param string $name
     * @param string $guardName
     * @param string|null $tenantId
     * @return KeystoneRole|null
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
     *
     * @param string|null $tenantId
     * @return Collection
     */
    public function getAllForTenant(?string $tenantId = null): Collection
    {
        if (!config('keystone.features.multi_tenant', false)) {
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
     *
     * @param Authenticatable $user
     * @param string|array $roles
     * @return void
     */
    public function assignToUser(Authenticatable $user, string|array $roles): void
    {
        $user->assignRole($roles);
    }

    /**
     * Remove role(s) from a user.
     *
     * @param Authenticatable $user
     * @param string|array $roles
     * @return void
     */
    public function removeFromUser(Authenticatable $user, string|array $roles): void
    {
        $user->removeRole($roles);
    }

    /**
     * Clear the role and permission cache.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cacheService->clearPermissionCache();
    }
}
