<?php

namespace BSPDX\Keystone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * KeystonePermission Model
 *
 * Represents a permission in the multi-tenant RBAC system.
 * Supports both global permissions (tenant_id = NULL) and tenant-specific permissions.
 *
 * @property int $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string $guard_name
 * @property string|null $title
 * @property string|null $description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static Builder withoutTenant()
 * @method static Builder global()
 * @method static Builder tenantSpecific()
 * @method static Builder forTenant($tenantId)
 */
class KeystonePermission extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'permissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'title',
        'description',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'guard_name' => 'web',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted(): void
    {
        parent::booted();

        // Add global scope for tenant isolation when multi-tenancy is enabled
        static::addGlobalScope('tenant', function (Builder $query) {
            if (config('keystone.features.multi_tenant', false) && auth()->check() && auth()->user()->tenant_id) {
                $tableName = $query->getModel()->getTable();
                $query->where(function ($q) use ($tableName) {
                    $q->where("{$tableName}.tenant_id", auth()->user()->tenant_id)
                      ->orWhereNull("{$tableName}.tenant_id"); // Allow access to global permissions (tenant_id = null)
                });
            }
        });

        // Auto-set tenant_id and guard_name when creating permissions
        static::creating(function ($permission) {
            // Set guard_name if not provided
            if (!isset($permission->guard_name)) {
                $permission->guard_name = 'web';
            }

            // Set tenant_id for multi-tenant mode
            if (config('keystone.features.multi_tenant', false) && auth()->check() && !isset($permission->tenant_id)) {
                $permission->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get the roles that have this permission.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            KeystoneRole::class,
            'role_has_permissions',
            'permission_id',
            'role_id'
        );
    }

    // ============================================
    // ROLE ASSIGNMENT METHODS
    // ============================================

    /**
     * Assign this permission to one or more roles.
     *
     * @param mixed ...$roles
     * @return $this
     */
    public function assignRole(...$roles): self
    {
        $roleModels = $this->convertToRoleModels($roles);

        $this->roles()->syncWithoutDetaching($roleModels->pluck('id'));
        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Sync the permission's roles (removes existing, adds new).
     *
     * @param mixed ...$roles
     * @return $this
     */
    public function syncRoles(...$roles): self
    {
        $roleModels = $this->convertToRoleModels($roles);

        $this->roles()->sync($roleModels->pluck('id'));
        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove this permission from one or more roles.
     *
     * @param mixed ...$roles
     * @return $this
     */
    public function removeRole(...$roles): self
    {
        $roleModels = $this->convertToRoleModels($roles);

        $this->roles()->detach($roleModels->pluck('id'));
        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Check if this permission is assigned to a specific role.
     *
     * @param mixed $role
     * @return bool
     */
    public function hasRole($role): bool
    {
        if (is_string($role)) {
            return $this->roles->contains('name', $role);
        }

        if ($role instanceof KeystoneRole) {
            return $this->roles->contains('id', $role->id);
        }

        if (is_int($role)) {
            return $this->roles->contains('id', $role);
        }

        return false;
    }

    /**
     * Check if this permission is assigned to any of the given roles.
     *
     * @param mixed ...$roles
     * @return bool
     */
    public function hasAnyRole(...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the names of all roles that have this permission.
     *
     * @return Collection
     */
    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Convert various role representations to KeystoneRole models.
     *
     * @param array $roles
     * @return Collection
     */
    protected function convertToRoleModels(array $roles): Collection
    {
        return collect($roles)->flatten()->map(function ($role) {
            if ($role instanceof KeystoneRole) {
                return $role;
            }

            if (is_string($role)) {
                return KeystoneRole::where('name', $role)->firstOrFail();
            }

            if (is_int($role)) {
                return KeystoneRole::findOrFail($role);
            }

            throw new \InvalidArgumentException('Invalid role type provided');
        });
    }

    /**
     * Clear cached permissions for all users with this permission.
     *
     * @return void
     */
    protected function forgetCachedPermissions(): void
    {
        // TODO: Implement cache clearing when caching layer is added
        // For now, this is a placeholder for future caching implementation
    }

    /**
     * Check if this is a global permission (accessible across all tenants).
     *
     * @return bool
     */
    public function isGlobal(): bool
    {
        return is_null($this->tenant_id);
    }

    /**
     * Check if this permission belongs to a specific tenant.
     *
     * @param string|int|null $tenantId
     * @return bool
     */
    public function belongsToTenant($tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }

    /**
     * Get display name (title if available, otherwise name).
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->title ?? $this->name;
    }

    // ============================================
    // QUERY SCOPES
    // ============================================

    /**
     * Scope a query to exclude the tenant scope.
     * Use sparingly and only for super-admin operations.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Scope a query to only return global permissions (tenant_id = NULL).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * Scope a query to only return tenant-specific permissions.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeTenantSpecific(Builder $query): Builder
    {
        return $query->whereNotNull('tenant_id');
    }

    /**
     * Scope a query to return permissions for a specific tenant.
     * Includes both global and tenant-specific permissions.
     *
     * @param Builder $query
     * @param string|int $tenantId
     * @return Builder
     */
    public function scopeForTenant(Builder $query, $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
    }
}
