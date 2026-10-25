<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\Contracts\PermissionServiceInterface;
use BSPDX\Keystone\Support\DefaultGuard;
use BSPDX\Keystone\Support\MultiTenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PermissionService implements PermissionServiceInterface
{
    /**
     * Get all permissions with their roles, ordered by name then id.
     */
    public function getAllWithRoles(): Collection
    {
        return $this->orderedWithRoles()->get();
    }

    /**
     * One page of the permissions getAllWithRoles() returns.
     *
     * @return LengthAwarePaginator<int, KeystonePermission>
     */
    public function paginateWithRoles(int $perPage, ?int $page = null): LengthAwarePaginator
    {
        return $this->orderedWithRoles()->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return Builder<KeystonePermission>
     */
    protected function orderedWithRoles(): Builder
    {
        return KeystonePermission::with('roles')->orderBy('name')->orderBy('id');
    }

    /**
     * Create a new permission.
     */
    public function create(string $name, ?string $guardName = null): KeystonePermission
    {
        return KeystonePermission::create([
            'name' => $name,
            'guard_name' => DefaultGuard::resolve($guardName),
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
    public function syncToUser(KeystoneUser $user, array $permissions): void
    {
        $user->syncPermissions($permissions);
    }

    /**
     * Get direct permissions assigned to a user.
     */
    public function getUserPermissions(KeystoneUser $user): Collection
    {
        return $user->permissions()->get();
    }

    /**
     * Get all permissions for a user (including via roles).
     */
    public function getAllUserPermissions(KeystoneUser $user): Collection
    {
        return $user->getAllPermissions();
    }

    /**
     * Find a permission by name.
     */
    public function findByName(string $name, ?string $guardName = null, ?string $tenantId = null): ?KeystonePermission
    {
        $query = KeystonePermission::where('name', $name)->where('guard_name', DefaultGuard::resolve($guardName));

        if (MultiTenancy::enabled()) {
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            // With a reference tenant (the argument, or the caller's tenant
            // through the tenant scope) its own row wins over a global row.
            // Without one, the global row wins over any tenant's row.
            $hasReferenceTenant = $tenantId !== null || auth()->user()?->getAttribute('tenant_id') !== null;
            $query->orderByRaw($hasReferenceTenant ? 'tenant_id IS NULL' : 'tenant_id IS NOT NULL');
        }

        return $query->orderBy('id')->first();
    }

    /**
     * Get all permissions for a specific tenant.
     */
    public function getAllForTenant(?string $tenantId = null): Collection
    {
        if (! MultiTenancy::enabled()) {
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
    public function assignToUser(KeystoneUser $user, string|array $permissions): void
    {
        $user->givePermissionTo($permissions);
    }

    /**
     * Remove permission(s) from a user.
     */
    public function removeFromUser(KeystoneUser $user, string|array $permissions): void
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
