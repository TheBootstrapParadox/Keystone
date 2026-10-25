<?php

namespace BSPDX\Keystone\Models;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Exceptions\ReservedRoleName;
use BSPDX\Keystone\Exceptions\RoleAlreadyExists;
use BSPDX\Keystone\Models\Concerns\ResolvesByNameForTenant;
use BSPDX\Keystone\Support\DefaultGuard;
use BSPDX\Keystone\Support\MultiTenancy;
use BSPDX\Keystone\Support\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * KeystoneRole Model
 *
 * Represents a role in the multi-tenant RBAC system.
 * Supports both global roles (tenant_id = NULL) and tenant-specific roles.
 *
 * @method static Builder withoutTenant()
 * @method static Builder global()
 * @method static Builder tenantSpecific()
 * @method static Builder forTenant($tenantId)
 */
class KeystoneRole extends Model
{
    use ResolvesByNameForTenant;

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
            if (! MultiTenancy::enabled()) {
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
            if (! isset($role->guard_name)) {
                $role->guard_name = DefaultGuard::resolve();
            }

            // Set tenant_id for multi-tenant mode
            if (MultiTenancy::enabled() &&
                auth()->check() &&
                ! isset($role->tenant_id)) {
                $role->tenant_id = auth()->user()->tenant_id;
            }

            static::guardReservedName($role);
            static::guardDuplicate($role);
        });

        static::updating(function (self $role) {
            static::guardReservedName($role);
            static::guardDuplicate($role);
        });
    }

    /**
     * @throws RoleAlreadyExists
     */
    protected static function guardDuplicate(self $role): void
    {
        if ($role->duplicatesAnotherRow()) {
            throw RoleAlreadyExists::create($role->name, $role->guard_name, $role->tenant_id);
        }
    }

    /**
     * Refuse to store the super-admin name on a tenant-scoped role.
     *
     * Only a global role confers super-admin status, so a tenant role with that
     * name would be misleading at best.
     *
     * @throws ReservedRoleName
     */
    protected static function guardReservedName(self $role): void
    {
        if (MultiTenancy::enabled()
            && $role->tenant_id !== null
            && $role->name === config('keystone.rbac.super_admin_role', 'super-admin')) {
            throw ReservedRoleName::forTenant($role->name);
        }
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * The permissions that belong to the role.
     *
     * @return BelongsToMany<KeystonePermission, $this>
     */
    public function permissions(): BelongsToMany
    {
        // Not narrowed by the logged-in caller's tenant scope: what a row holds
        // does not depend on who is asking.
        return $this->belongsToMany(
            KeystonePermission::class,
            'role_has_permissions',
            'role_id',
            'permission_id'
        )->withoutGlobalScope('tenant')->withTimestamps();
    }

    /**
     * The users that have this role.
     *
     * @return MorphToMany<Model&KeystoneUser, $this>
     */
    public function users(): MorphToMany
    {
        $relation = $this->morphedByMany(
            UserModel::className(),
            'model',
            'model_has_roles',
            'role_id',
            'model_id'
        )->withTimestamps();

        if (MultiTenancy::enabled()) {
            $relation->withPivot('tenant_id');
        }

        return $relation;
    }

    // ============================================
    // PERMISSION METHODS
    // ============================================

    /**
     * Assign permissions to this role.
     */
    public function givePermissionTo(string|int|KeystonePermission|iterable ...$permissions): static
    {
        $permissionModels = $this->convertToPermissionModels($permissions);

        $this->permissions()->syncWithoutDetaching($permissionModels->pluck('id'));
        $this->unsetRelation('permissions'); // Force reload of permissions relationship

        return $this;
    }

    /**
     * Sync permissions for this role.
     */
    public function syncPermissions(string|int|KeystonePermission|iterable ...$permissions): static
    {
        $permissionModels = $this->convertToPermissionModels($permissions);

        $this->permissions()->sync($permissionModels->pluck('id'));
        $this->unsetRelation('permissions');

        return $this;
    }

    /**
     * Revoke permissions from this role.
     */
    public function revokePermissionTo(string|int|KeystonePermission|iterable ...$permissions): static
    {
        $permissionModels = $this->convertToPermissionModels($permissions);

        $this->permissions()->detach($permissionModels->pluck('id'));
        $this->unsetRelation('permissions');

        return $this;
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermissionTo(string|int|KeystonePermission $permission, ?string $guardName = null): bool
    {
        if (is_int($permission)) {
            return $this->permissions->contains('id', $permission);
        }

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
     *
     * @return Collection<int, string>
     */
    public function getPermissionNames(): Collection
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
     * Convert permission names or models to models, resolving names in this role's tenant.
     *
     * @param  array<int, mixed>  $permissions
     * @return Collection<int, KeystonePermission>
     */
    protected function convertToPermissionModels(array $permissions): Collection
    {
        return collect($permissions)->flatten()->map(
            fn ($permission) => KeystonePermission::resolveForTenant($permission, $this->keystoneTenantId())
        );
    }

    /**
     * Determine if this role is the super admin role.
     *
     * In multi-tenant mode only a global role qualifies.
     */
    public function isSuperAdmin(): bool
    {
        if ($this->name !== config('keystone.rbac.super_admin_role', 'super-admin')) {
            return false;
        }

        return ! MultiTenancy::enabled() || $this->tenant_id === null;
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
    public function belongsToTenant(?string $tenantId): bool
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
     * Scope a query to roles belonging to a specific tenant only.
     * Global roles (tenant_id = NULL) are excluded; use global() for those.
     * Respects the tenant global scope — chain after withoutTenant() for cross-tenant reads.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
    }
}
