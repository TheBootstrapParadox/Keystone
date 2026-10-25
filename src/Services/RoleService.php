<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Exceptions\CannotDeleteSuperAdminRole;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;
use BSPDX\Keystone\Services\Contracts\RoleServiceInterface;
use BSPDX\Keystone\Support\DefaultGuard;
use BSPDX\Keystone\Support\MultiTenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RoleService implements RoleServiceInterface
{
    /**
     * Create a new role service instance.
     */
    public function __construct(protected CacheServiceInterface $cacheService) {}

    /**
     * Get all roles with their permissions, ordered by name then id.
     */
    public function getAllWithPermissions(): Collection
    {
        return $this->orderedWithPermissions()->get();
    }

    /**
     * One page of the roles getAllWithPermissions() returns.
     *
     * @return LengthAwarePaginator<int, KeystoneRole>
     */
    public function paginateWithPermissions(int $perPage, ?int $page = null): LengthAwarePaginator
    {
        return $this->orderedWithPermissions()->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return Builder<KeystoneRole>
     */
    protected function orderedWithPermissions(): Builder
    {
        return KeystoneRole::with('permissions')->orderBy('name')->orderBy('id');
    }

    /**
     * Create a new role.
     */
    public function create(string $name, ?string $guardName = null): KeystoneRole
    {
        return KeystoneRole::create([
            'name' => $name,
            'guard_name' => DefaultGuard::resolve($guardName),
        ]);
    }

    /**
     * Delete a role.
     *
     * @throws CannotDeleteSuperAdminRole
     */
    public function delete(KeystoneRole $role): void
    {
        if ($role->isSuperAdmin()) {
            throw new CannotDeleteSuperAdminRole;
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
    public function getUserRoles(KeystoneUser $user): Collection
    {
        return $user->roles()->get();
    }

    /**
     * Find a role by name.
     */
    public function findByName(string $name, ?string $guardName = null, ?string $tenantId = null): ?KeystoneRole
    {
        $query = KeystoneRole::where('name', $name)->where('guard_name', DefaultGuard::resolve($guardName));

        if (MultiTenancy::enabled()) {
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            // A tenant row wins over a global row with the same name
            $query->orderByRaw('tenant_id IS NULL');
        }

        return $query->orderBy('id')->first();
    }

    /**
     * Get all roles for a specific tenant.
     */
    public function getAllForTenant(?string $tenantId = null): Collection
    {
        if (! MultiTenancy::enabled()) {
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
    public function assignToUser(KeystoneUser $user, string|array $roles): void
    {
        $user->assignRole($roles);
    }

    /**
     * Remove role(s) from a user.
     */
    public function removeFromUser(KeystoneUser $user, string|array $roles): void
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
