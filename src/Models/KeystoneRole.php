<?php

namespace BSPDX\Keystone\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * @method $this givePermissionTo(...$permissions)
 * @method $this syncPermissions(...$permissions)
 * @method $this revokePermissionTo($permission)
 * @method bool hasPermissionTo($permission, ?string $guardName = null)
 * @method Collection getPermissionNames()
 */
class KeystoneRole extends Role
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'guard_name', 'title', 'description', 'tenant_id'];

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
                $query->where(function ($q) {
                    $q->where('tenant_id', auth()->user()->tenant_id)
                      ->orWhereNull('tenant_id'); // Allow access to global roles (tenant_id = null)
                });
            }
        });

        // Auto-set tenant_id when creating roles
        static::creating(function ($role) {
            if (config('keystone.features.multi_tenant', false) && auth()->check() && !isset($role->tenant_id)) {
                $role->tenant_id = auth()->user()->tenant_id;
            }
        });
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

    /**
     * Determine if this role is the super admin role.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->name === config('keystone.rbac.super_admin_role', 'super-admin');
    }

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
}
