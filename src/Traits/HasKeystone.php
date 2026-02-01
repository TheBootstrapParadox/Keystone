<?php

namespace BSPDX\Keystone\Traits;

use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Models\KeystonePermission;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

trait HasKeystone
{
    use HasApiTokens;
    use TwoFactorAuthenticatable;
    use InteractsWithPasskeys;

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
            if (!$this->hasRole($role)) {
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
            if (!$this->hasPermissionTo($permission)) {
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
    // TWO-FACTOR AUTHENTICATION METHODS
    // ============================================

    /**
     * Determine if the user has enabled two-factor authentication.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_secret) &&
               !is_null($this->two_factor_confirmed_at);
    }

    /**
     * Determine if 2FA is required for this user based on their roles.
     */
    public function requires2FA(): bool
    {
        $requiredRoles = config('keystone.two_factor.required_for_roles', []);

        if (empty($requiredRoles)) {
            return false;
        }

        return $this->hasAnyRole($requiredRoles);
    }

    // ============================================
    // PASSKEY METHODS
    // ============================================

    /**
     * Determine if the user has registered any passkeys.
     */
    public function hasPasskeysRegistered(): bool
    {
        return $this->passkeys()->exists();
    }

    /**
     * Determine if passkeys are required for this user based on their roles.
     */
    public function requiresPasskey(): bool
    {
        $requiredRoles = config('keystone.passkey.required_for_roles', []);

        if (empty($requiredRoles)) {
            return false;
        }

        return $this->hasAnyRole($requiredRoles);
    }

    /**
     * Check if user can use passwordless login.
     */
    public function canUsePasswordlessLogin(): bool
    {
        return ($this->allow_passkey_login && $this->hasPasskeysRegistered()) ||
               ($this->allow_totp_login && $this->hasTwoFactorEnabled());
    }

    // ============================================
    // AUTHENTICATION METHODS
    // ============================================

    /**
     * Get the user's authentication methods.
     */
    public function getAuthenticationMethods(): array
    {
        return [
            'password' => true,
            'two_factor' => $this->hasTwoFactorEnabled(),
            'passkey' => $this->hasPasskeysRegistered(),
        ];
    }

    /**
     * Get available authentication methods for this user.
     */
    public function getAvailableAuthMethods(): array
    {
        $methods = [];

        if ($this->require_password) {
            $methods[] = 'password';
        }

        if ($this->allow_passkey_login && $this->hasPasskeysRegistered()) {
            $methods[] = 'passkey';
        }

        if ($this->allow_totp_login && $this->hasTwoFactorEnabled()) {
            $methods[] = 'totp';
        }

        return $methods;
    }

    /**
     * Validate that at least one auth method is enabled.
     */
    public function hasValidAuthConfiguration(): bool
    {
        return $this->require_password ||
               ($this->allow_passkey_login && $this->hasPasskeysRegistered()) ||
               ($this->allow_totp_login && $this->hasTwoFactorEnabled());
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

    // ============================================
    // STATIC HELPER METHODS
    // ============================================

    /**
     * Get the auth preference fillable attributes.
     */
    public static function getAuthPreferenceFillable(): array
    {
        return [
            'allow_passkey_login',
            'allow_totp_login',
            'require_password',
        ];
    }

    /**
     * Get the auth preference cast attributes.
     */
    public static function getAuthPreferenceCasts(): array
    {
        return [
            'allow_passkey_login' => 'boolean',
            'allow_totp_login' => 'boolean',
            'require_password' => 'boolean',
        ];
    }
}
