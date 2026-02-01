<?php

namespace BSPDX\Keystone\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * @method $this assignRole(...$roles)
 * @method $this removeRole(...$role)
 * @method $this syncRoles(...$roles)
 * @method bool hasRole($roles, ?string $guard = null)
 * @method bool hasAnyRole(...$roles)
 * @method Collection getRoleNames()
 */
class KeystonePermission extends Permission
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
                      ->orWhereNull('tenant_id'); // Allow access to global permissions (tenant_id = null)
                });
            }
        });

        // Auto-set tenant_id when creating permissions
        static::creating(function ($permission) {
            if (config('keystone.features.multi_tenant', false) && auth()->check() && !isset($permission->tenant_id)) {
                $permission->tenant_id = auth()->user()->tenant_id;
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
