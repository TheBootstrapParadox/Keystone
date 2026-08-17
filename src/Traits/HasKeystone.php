<?php

namespace BSPDX\Keystone\Traits;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HasKeystone
{
    // ============================================
    // QUERY SCOPES
    // ============================================

    /**
     * Scope a query to users with the given role.
     */
    public function scopeRole(Builder $query, string|array $roles): Builder
    {
        return $query->whereHas('roles', function ($q) use ($roles) {
            $q->whereIn('name', (array) $roles);
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * User's roles relationship with tenant filtering
     */
    public function roles(): MorphToMany
    {
        $relation = $this->morphToMany(
            KeystoneRole::class,
            'model',
            'model_has_roles',
            'model_id',
            'role_id'
        )->withPivot('tenant_id')->withTimestamps();

        // Apply tenant filtering on pivot table
        if (config('keystone.features.multi_tenant', false)) {
            if ($this->tenant_id) {
                // User has a tenant: show both tenant-specific and global roles
                $relation->wherePivotIn('tenant_id', [$this->tenant_id, null]);
            }
            // If user has no tenant (tenant_id = NULL), show all roles (no filtering needed)
            // This allows users without tenants to have roles in non-multi-tenant scenarios
        }

        return $relation;
    }

    /**
     * User's direct permissions (not via roles)
     */
    public function permissions(): MorphToMany
    {
        $relation = $this->morphToMany(
            KeystonePermission::class,
            'model',
            'model_has_permissions',
            'model_id',
            'permission_id'
        )->withPivot('tenant_id')->withTimestamps();

        // Apply tenant filtering
        if (config('keystone.features.multi_tenant', false)) {
            if ($this->tenant_id) {
                // User has a tenant: show both tenant-specific and global permissions
                $relation->wherePivotIn('tenant_id', [$this->tenant_id, null]);
            }
            // If user has no tenant (tenant_id = NULL), show all permissions (no filtering needed)
            // This allows users without tenants to have permissions in non-multi-tenant scenarios
        }

        return $relation;
    }

    // ============================================
    // ROLE ASSIGNMENT METHODS
    // ============================================

    /**
     * Assign roles to user with automatic tenant_id population
     */
    public function assignRole(...$roles): self
    {
        $roleModels = $this->convertToRoleModels($roles);

        $pivotData = [];
        foreach ($roleModels as $role) {
            $pivotData[$role->id] = [
                'tenant_id' => $this->tenant_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->roles()->syncWithoutDetaching($pivotData);
        $this->forgetCachedPermissions();
        $this->unsetRelation('roles'); // Force reload of roles relationship

        return $this;
    }

    /**
     * Remove roles from user (tenant-scoped)
     */
    public function removeRole(...$roles): self
    {
        $roleModels = $this->convertToRoleModels($roles);

        // Only remove roles from current tenant context
        DB::table('model_has_roles')
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->id)
            ->whereIn('role_id', $roleModels->pluck('id'))
            ->where('tenant_id', $this->tenant_id)
            ->delete();

        $this->forgetCachedPermissions();
        $this->unsetRelation('roles'); // Force reload of roles relationship

        return $this;
    }

    /**
     * Sync roles for user (tenant-scoped)
     */
    public function syncRoles(...$roles): self
    {
        $roleModels = $this->convertToRoleModels($roles);

        // Remove all current tenant roles
        DB::table('model_has_roles')
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->id)
            ->where('tenant_id', $this->tenant_id)
            ->delete();

        // Add new roles
        if ($roleModels->isNotEmpty()) {
            $this->assignRole(...$roleModels);
        } else {
            // If removing all roles, still need to unset the relationship
            $this->unsetRelation('roles');
        }

        return $this;
    }

    // ============================================
    // PERMISSION ASSIGNMENT METHODS
    // ============================================

    /**
     * Give direct permissions to user
     */
    public function givePermissionTo(...$permissions): self
    {
        $permissionModels = $this->convertToPermissionModels($permissions);

        $pivotData = [];
        foreach ($permissionModels as $permission) {
            $pivotData[$permission->id] = [
                'tenant_id' => $this->tenant_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->permissions()->syncWithoutDetaching($pivotData);
        $this->forgetCachedPermissions();
        $this->unsetRelation('permissions'); // Force reload of permissions relationship

        return $this;
    }

    /**
     * Revoke direct permissions from user
     */
    public function revokePermissionTo(...$permissions): self
    {
        $permissionModels = $this->convertToPermissionModels($permissions);

        DB::table('model_has_permissions')
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->id)
            ->whereIn('permission_id', $permissionModels->pluck('id'))
            ->where('tenant_id', $this->tenant_id)
            ->delete();

        $this->forgetCachedPermissions();
        $this->unsetRelation('permissions'); // Force reload of permissions relationship

        return $this;
    }

    /**
     * Sync direct permissions for user
     */
    public function syncPermissions(...$permissions): self
    {
        DB::table('model_has_permissions')
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->id)
            ->where('tenant_id', $this->tenant_id)
            ->delete();

        $permissionModels = $this->convertToPermissionModels($permissions);
        if ($permissionModels->isNotEmpty()) {
            $this->givePermissionTo(...$permissionModels);
        } else {
            // If removing all permissions, still need to unset the relationship
            $this->unsetRelation('permissions');
        }

        return $this;
    }

    // ============================================
    // PERMISSION CHECKING METHODS
    // ============================================

    /**
     * Check if user has a specific role
     */
    public function hasRole($roles, string $guard = 'web'): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (is_string($roles)) {
            return $this->roles
                ->where('guard_name', $guard)
                ->contains('name', $roles);
        }

        if ($roles instanceof KeystoneRole) {
            return $this->roles->contains('id', $roles->id);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $guard)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(...$roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles(...$roles): bool
    {
        foreach ($roles as $role) {
            if (! $this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermissionTo($permission, string $guard = 'web'): bool
    {
        // Super-admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check direct permissions
        $permissionName = $permission instanceof KeystonePermission
            ? $permission->name
            : $permission;

        if ($this->permissions->where('guard_name', $guard)->contains('name', $permissionName)) {
            return true;
        }

        // Check permissions via roles
        return $this->hasPermissionViaRole($permission, $guard);
    }

    /**
     * Check if user has permission via any of their roles
     */
    protected function hasPermissionViaRole($permission, string $guard = 'web'): bool
    {
        $permissionName = $permission instanceof KeystonePermission
            ? $permission->name
            : $permission;

        return $this->roles
            ->where('guard_name', $guard)
            ->flatMap->permissions
            ->where('guard_name', $guard)
            ->contains('name', $permissionName);
    }

    /**
     * Get all permissions for user (direct + via roles)
     */
    public function getAllPermissions(): Collection
    {
        $permissions = $this->permissions;

        $this->roles->each(function ($role) use (&$permissions) {
            $permissions = $permissions->merge($role->permissions);
        });

        return $permissions->unique('id');
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(...$permissions): bool
    {
        // Super-admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions(...$permissions): bool
    {
        // Super-admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if (! $this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has a direct permission (not via role)
     */
    public function hasDirectPermission($permission, string $guard = 'web'): bool
    {
        // Super-admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissionName = $permission instanceof KeystonePermission
            ? $permission->name
            : $permission;

        return $this->permissions
            ->where('guard_name', $guard)
            ->contains('name', $permissionName);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Convert mixed role input to KeystoneRole models
     */
    protected function convertToRoleModels($roles): Collection
    {
        return collect($roles)->flatten()->map(function ($role) {
            if ($role instanceof KeystoneRole) {
                return $role;
            }

            return KeystoneRole::where('name', $role)->firstOrFail();
        });
    }

    /**
     * Convert mixed permission input to KeystonePermission models
     */
    protected function convertToPermissionModels($permissions): Collection
    {
        return collect($permissions)->flatten()->map(function ($permission) {
            if ($permission instanceof KeystonePermission) {
                return $permission;
            }

            return KeystonePermission::where('name', $permission)->firstOrFail();
        });
    }

    /**
     * Clear cached permissions for this user
     */
    protected function forgetCachedPermissions(): void
    {
        Cache::forget("user_permissions_{$this->id}");
    }

    // ============================================
    // ADMIN METHODS
    // ============================================

    /**
     * Determine if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        $superAdminRole = config('keystone.rbac.super_admin_role', 'super-admin');

        return $this->roles->contains('name', $superAdminRole);
    }

    /**
     * Check if user can bypass permission checks (super admin).
     */
    public function canBypassPermissions(): bool
    {
        return $this->isSuperAdmin();
    }
}
