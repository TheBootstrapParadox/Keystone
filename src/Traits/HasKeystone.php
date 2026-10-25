<?php

namespace BSPDX\Keystone\Traits;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Support\DefaultGuard;
use BSPDX\Keystone\Support\MultiTenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasKeystone
{
    /**
     * Remove a deleted user's role and permission assignments (every tenant).
     *
     * Soft-deleted users keep them so a restore brings their access back; they
     * are removed on force-delete.
     */
    public static function bootHasKeystone(): void
    {
        static::deleted(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            foreach (['model_has_roles', 'model_has_permissions'] as $table) {
                $model->keystoneAssignments($table)->delete();
            }
        });
    }

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
     * User's roles.
     *
     * Resolved from this user's own assignments only, whoever is logged in: the
     * roles' tenant scope is removed, and in multi-tenant mode a user with a
     * tenant sees assignments stored for that tenant or with no tenant.
     *
     * @return MorphToMany<KeystoneRole, $this>
     */
    public function roles(): MorphToMany
    {
        return $this->keystoneAssignmentRelation(KeystoneRole::class, 'model_has_roles', 'role_id');
    }

    /**
     * User's direct permissions (not via roles), resolved like roles().
     *
     * @return MorphToMany<KeystonePermission, $this>
     */
    public function permissions(): MorphToMany
    {
        return $this->keystoneAssignmentRelation(KeystonePermission::class, 'model_has_permissions', 'permission_id');
    }

    /**
     * @template TRelated of KeystoneRole|KeystonePermission
     *
     * @param  class-string<TRelated>  $related
     * @return MorphToMany<TRelated, $this>
     */
    protected function keystoneAssignmentRelation(string $related, string $table, string $relatedPivotKey): MorphToMany
    {
        $relation = $this->morphToMany($related, 'model', $table, 'model_id', $relatedPivotKey)
            ->withoutGlobalScope('tenant')
            ->withTimestamps();

        if (MultiTenancy::enabled()) {
            $relation->withPivot('tenant_id');
            $this->constrainToVisibleAssignments($relation, $table);
        }

        return $relation;
    }

    /**
     * Every row of an assignment pivot that belongs to this user (all tenants).
     */
    protected function keystoneAssignments(string $table): QueryBuilder
    {
        return DB::table($table)
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->getKey());
    }

    /**
     * This user's rows of an assignment pivot in the user's own tenant (all
     * rows in single-tenant mode). Removal and replacement act on these.
     */
    protected function keystoneAssignmentsInTenant(string $table): QueryBuilder
    {
        return $this->keystoneAssignments($table)
            ->when(MultiTenancy::enabled(), fn (QueryBuilder $query) => $query->where('tenant_id', $this->tenant_id));
    }

    /**
     * Limit a query on an assignment pivot to rows this user can see: in
     * multi-tenant mode, a user with a tenant sees rows stored for that tenant
     * or with no tenant; a user without a tenant sees all of their rows.
     *
     * @param  MorphToMany<*, *>|\Illuminate\Database\Query\Builder  $query
     */
    protected function constrainToVisibleAssignments($query, string $table): void
    {
        if (! MultiTenancy::enabled() || $this->tenant_id === null) {
            return;
        }

        $query->where(fn ($q) => $q->where("{$table}.tenant_id", $this->tenant_id)->orWhereNull("{$table}.tenant_id"));
    }

    /**
     * Attach the given roles/permissions that this user cannot already see,
     * stamping the pivot with the user's tenant in multi-tenant mode.
     *
     * @template TModel of KeystoneRole|KeystonePermission
     *
     * @param  Collection<int, TModel>  $models
     */
    protected function attachMissingAssignments(string $table, string $relatedPivotKey, Collection $models): void
    {
        $ids = $models->pluck('id')->unique()->values();

        $existing = $this->keystoneAssignments($table)->whereIn($relatedPivotKey, $ids);
        $this->constrainToVisibleAssignments($existing, $table);

        $missing = $ids->diff($existing->pluck($relatedPivotKey));

        if ($missing->isEmpty()) {
            return;
        }

        $now = now();
        $tenant = MultiTenancy::enabled() ? ['tenant_id' => $this->tenant_id] : [];

        DB::table($table)->insert($missing->map(fn ($id) => [
            $relatedPivotKey => $id,
            'model_type' => $this->getMorphClass(),
            'model_id' => $this->getKey(),
            ...$tenant,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());
    }

    // ============================================
    // ROLE ASSIGNMENT METHODS
    // ============================================

    /**
     * Add roles to the user, stamping the assignment with the user's tenant
     * in multi-tenant mode.
     */
    public function assignRole(string|int|KeystoneRole|iterable ...$roles): static
    {
        $this->attachMissingAssignments('model_has_roles', 'role_id', $this->convertToRoleModels($roles));
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Remove roles from the user (only assignments in the user's tenant).
     */
    public function removeRole(string|int|KeystoneRole|iterable ...$roles): static
    {
        $roleModels = $this->convertToRoleModels($roles);

        $this->keystoneAssignmentsInTenant('model_has_roles')
            ->whereIn('role_id', $roleModels->pluck('id'))
            ->delete();

        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Replace the user's roles (in the user's tenant) with exactly the given set.
     *
     * Every role is resolved before anything is removed, and the removal and
     * the new assignments share one transaction.
     */
    public function syncRoles(string|int|KeystoneRole|iterable ...$roles): static
    {
        $this->replaceAssignments('model_has_roles', 'role_id', $this->convertToRoleModels($roles));
        $this->unsetRelation('roles');

        return $this;
    }

    // ============================================
    // PERMISSION ASSIGNMENT METHODS
    // ============================================

    /**
     * Give direct permissions to the user.
     */
    public function givePermissionTo(string|int|KeystonePermission|iterable ...$permissions): static
    {
        $this->attachMissingAssignments('model_has_permissions', 'permission_id', $this->convertToPermissionModels($permissions));
        $this->unsetRelation('permissions');

        return $this;
    }

    /**
     * Revoke direct permissions from the user (only assignments in the user's tenant).
     */
    public function revokePermissionTo(string|int|KeystonePermission|iterable ...$permissions): static
    {
        $permissionModels = $this->convertToPermissionModels($permissions);

        $this->keystoneAssignmentsInTenant('model_has_permissions')
            ->whereIn('permission_id', $permissionModels->pluck('id'))
            ->delete();

        $this->unsetRelation('permissions');

        return $this;
    }

    /**
     * Replace the user's direct permissions (in the user's tenant) with
     * exactly the given set, all-or-nothing like syncRoles().
     */
    public function syncPermissions(string|int|KeystonePermission|iterable ...$permissions): static
    {
        $this->replaceAssignments('model_has_permissions', 'permission_id', $this->convertToPermissionModels($permissions));
        $this->unsetRelation('permissions');

        return $this;
    }

    /**
     * Delete this user's assignments in the user's tenant and attach the given
     * models, in one transaction.
     *
     * @template TModel of KeystoneRole|KeystonePermission
     *
     * @param  Collection<int, TModel>  $models
     */
    protected function replaceAssignments(string $table, string $relatedPivotKey, Collection $models): void
    {
        DB::transaction(function () use ($table, $relatedPivotKey, $models) {
            $this->keystoneAssignmentsInTenant($table)->delete();

            if ($models->isNotEmpty()) {
                $this->attachMissingAssignments($table, $relatedPivotKey, $models);
            }
        });
    }

    // ============================================
    // PERMISSION CHECKING METHODS
    // ============================================

    /**
     * Check if user has a specific role.
     *
     * Role checks are literal: super-admins only "have" roles they actually hold.
     * The super-admin bypass applies to permission checks, the Gate, the
     * role/permission middleware, and AuthorizationService — not here.
     */
    public function hasRole(string|int|KeystoneRole|iterable $roles, ?string $guard = null): bool
    {
        $guard = DefaultGuard::resolve($guard);

        if (is_string($roles)) {
            return $this->roles
                ->where('guard_name', $guard)
                ->contains('name', $roles);
        }

        if ($roles instanceof KeystoneRole) {
            return $this->roles->contains('id', $roles->id);
        }

        if (is_int($roles)) {
            return $this->roles->contains('id', $roles);
        }

        foreach ($roles as $role) {
            if ($this->hasRole($role, $guard)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(string|int|KeystoneRole|iterable ...$roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles(string|int|KeystoneRole|iterable ...$roles): bool
    {
        // Flatten so an array argument means "all of these", never "any of these"
        foreach (collect($roles)->flatten() as $role) {
            if (! $this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermissionTo(string|int|KeystonePermission $permission, ?string $guard = null): bool
    {
        $guard = DefaultGuard::resolve($guard);

        // Super-admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check direct permissions
        $permissionName = $this->keystonePermissionName($permission);

        if ($this->permissions->where('guard_name', $guard)->contains('name', $permissionName)) {
            return true;
        }

        // Check permissions via roles
        return $this->hasPermissionViaRole($permission, $guard);
    }

    /**
     * Check if user has permission via any of their roles
     */
    protected function hasPermissionViaRole(string|int|KeystonePermission $permission, ?string $guard = null): bool
    {
        $guard = DefaultGuard::resolve($guard);

        $permissionName = $this->keystonePermissionName($permission);

        $this->loadMissing('roles.permissions');

        return $this->roles
            ->where('guard_name', $guard)
            ->flatMap(fn (KeystoneRole $role) => $role->permissions)
            ->where('guard_name', $guard)
            ->contains('name', $permissionName);
    }

    /**
     * Get all permissions for user (direct + via roles)
     *
     * @return Collection<int, KeystonePermission>
     */
    public function getAllPermissions(): Collection
    {
        $this->loadMissing('roles.permissions');

        $permissions = $this->permissions;

        $this->roles->each(function (KeystoneRole $role) use (&$permissions) {
            $permissions = $permissions->merge($role->permissions);
        });

        return $permissions->unique('id');
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(string|int|KeystonePermission|iterable ...$permissions): bool
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
    public function hasAllPermissions(string|int|KeystonePermission|iterable ...$permissions): bool
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
     * Check if user has a direct permission (not via role).
     *
     * Literal, like hasRole(): a super-admin only "has" direct permissions they
     * actually hold. AuthorizationService::userHasDirectPermission() keeps the
     * bypass.
     */
    public function hasDirectPermission(string|int|KeystonePermission $permission, ?string $guard = null): bool
    {
        $guard = DefaultGuard::resolve($guard);

        $permissionName = $this->keystonePermissionName($permission);

        return $this->permissions
            ->where('guard_name', $guard)
            ->contains('name', $permissionName);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Convert mixed role input to KeystoneRole models, resolving names in this user's tenant
     *
     * @param  iterable<mixed>  $roles
     * @return Collection<int, KeystoneRole>
     */
    protected function convertToRoleModels(iterable $roles): Collection
    {
        return collect($roles)->flatten()->map(
            fn ($role) => KeystoneRole::resolveForTenant($role, $this->keystoneUserTenantId())
        );
    }

    /**
     * Convert mixed permission input to KeystonePermission models, resolving names in this user's tenant
     *
     * @param  iterable<mixed>  $permissions
     * @return Collection<int, KeystonePermission>
     */
    protected function convertToPermissionModels(iterable $permissions): Collection
    {
        return collect($permissions)->flatten()->map(
            fn ($permission) => KeystonePermission::resolveForTenant($permission, $this->keystoneUserTenantId())
        );
    }

    /**
     * The name of a permission given as a name, model, or integer ID (null if no such ID).
     */
    protected function keystonePermissionName(string|int|KeystonePermission $permission): ?string
    {
        if ($permission instanceof KeystonePermission) {
            return $permission->name;
        }

        if (is_int($permission)) {
            return KeystonePermission::withoutGlobalScope('tenant')->whereKey($permission)->value('name');
        }

        return $permission;
    }

    /**
     * The user's tenant for name resolution (null when multi-tenancy is off).
     */
    protected function keystoneUserTenantId(): ?string
    {
        return MultiTenancy::enabled() ? $this->tenant_id : null;
    }

    // ============================================
    // ADMIN METHODS
    // ============================================

    /**
     * Determine if the user is a super admin.
     *
     * The user must hold the super-admin role on the default guard; in
     * multi-tenant mode that role must also be global.
     */
    public function isSuperAdmin(): bool
    {
        $guard = DefaultGuard::resolve();

        return $this->roles->contains(
            fn (KeystoneRole $role) => $role->guard_name === $guard && $role->isSuperAdmin()
        );
    }

    /**
     * Check if user can bypass permission checks (super admin).
     */
    public function canBypassPermissions(): bool
    {
        return $this->isSuperAdmin();
    }
}
