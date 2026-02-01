<?php

namespace BSPDX\Keystone\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class KeystoneRole extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'guard_name', 'title', 'description', 'tenant_id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        parent::booted();

        // Add global scope for tenant isolation when multi-tenancy is enabled
        static::addGlobalScope('tenant', function (Builder $query) {
            if (!config('keystone.features.multi_tenant', false)) {
                return;
            }

            if (auth()->check() && auth()->user()->tenant_id) {
                $tableName = $query->getModel()->getTable();
                $query->where(function ($q) use ($tableName) {
                    $q->where("{$tableName}.tenant_id", auth()->user()->tenant_id)
                      ->orWhereNull("{$tableName}.tenant_id"); // Include global roles
                });
            }
        });

        // Auto-set tenant_id and guard_name when creating roles
        static::creating(function ($role) {
            // Set guard_name if not provided
            if (!isset($role->guard_name)) {
                $role->guard_name = 'web';
            }

            // Set tenant_id for multi-tenant mode
            if (config('keystone.features.multi_tenant', false) &&
                auth()->check() &&
                !isset($role->tenant_id)) {
                $role->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * The permissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            KeystonePermission::class,
            'role_has_permissions',
            'role_id',
            'permission_id'
        );
    }

    /**
     * The users that have this role.
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            config('keystone.user_model', 'App\Models\User'),
            'model',
            'model_has_roles',
            'role_id',
            'model_id'
        )->withPivot('tenant_id')->withTimestamps();
    }

    // ============================================
    // PERMISSION METHODS
    // ============================================

    /**
     * Assign permissions to this role.
     */
    public function givePermissionTo(...$permissions): self
    {
        $permissionModels = collect($permissions)->flatten()->map(function ($permission) {
            if ($permission instanceof KeystonePermission) {
                return $permission;
            }
            return KeystonePermission::where('name', $permission)->firstOrFail();
        });

        $this->permissions()->syncWithoutDetaching($permissionModels->pluck('id'));
        $this->unsetRelation('permissions'); // Force reload of permissions relationship

        return $this;
    }

    /**
     * Sync permissions for this role.
     */
    public function syncPermissions(...$permissions): self
    {
        $permissionModels = collect($permissions)->flatten()->map(function ($permission) {
            if ($permission instanceof KeystonePermission) {
                return $permission;
            }
            return KeystonePermission::where('name', $permission)->firstOrFail();
        });

        $this->permissions()->sync($permissionModels->pluck('id'));

        return $this;
    }

    /**
     * Revoke a permission from this role.
     */
    public function revokePermissionTo($permission): self
    {
        $permissionModel = $permission instanceof KeystonePermission
            ? $permission
            : KeystonePermission::where('name', $permission)->firstOrFail();

        $this->permissions()->detach($permissionModel->id);

        return $this;
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        $guardName = $guardName ?? $this->guard_name;

        $permissionName = $permission instanceof KeystonePermission
            ? $permission->name
            : $permission;

        return $this->permissions
            ->where('guard_name', $guardName)
            ->contains('name', $permissionName);
    }

    /**
     * Get all permission names for this role.
     */
    public function getPermissionNames(): \Illuminate\Support\Collection
    {
        return $this->permissions->pluck('name');
    }

    // ============================================
    // ATTRIBUTE ACCESSORS
    // ============================================

    /**
     * Get display name (title if available, otherwise name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->title ?? $this->name;
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Determine if this role is the super admin role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->name === config('keystone.rbac.super_admin_role', 'super-admin');
    }

    /**
     * Determine if this is a global role (no tenant_id).
     */
    public function isGlobal(): bool
    {
        return is_null($this->tenant_id);
    }

    /**
     * Determine if this role belongs to a specific tenant.
     */
    public function belongsToTenant($tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }

    // ============================================
    // QUERY SCOPES
    // ============================================

    /**
     * Scope a query to exclude the tenant scope.
     * Use sparingly and only for super-admin operations.
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Scope a query to only include global roles.
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * Scope a query to only include tenant-specific roles.
     */
    public function scopeTenantSpecific(Builder $query): Builder
    {
        return $query->whereNotNull('tenant_id');
    }

    /**
     * Scope a query to a specific tenant.
     */
    public function scopeForTenant(Builder $query, $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
