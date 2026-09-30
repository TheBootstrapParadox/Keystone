<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Support\DefaultGuard;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use WeakMap;

/**
 * PermissionRegistrar Service
 *
 * Registers all permissions with Laravel's Gate system, enabling:
 * - @can('permission.name') Blade directive
 * - Gate::allows('permission.name') in controllers
 * - $this->authorize('permission.name') in controllers
 * - Policy integration
 */
class PermissionRegistrar
{
    /**
     * The cache repository instance.
     */
    protected CacheRepository $cache;

    /**
     * The cache key for storing permissions.
     */
    protected string $cacheKey = 'keystone.permissions.all.v2';

    /**
     * Gates that already carry Keystone's callback.
     *
     * @var WeakMap<Gate, true>
     */
    protected WeakMap $registeredGates;

    public function __construct(CacheRepository $cache)
    {
        $this->cache = $cache;
        $this->registeredGates = new WeakMap;
    }

    /**
     * Get the cache TTL (in seconds) for permissions.
     *
     * Read from config at call time rather than cached on the instance so the
     * configured value is honored even though this registrar is a singleton.
     */
    protected function cacheExpiration(): int
    {
        return (int) (config('keystone.rbac.cache_expiration') ?? 86400);
    }

    /**
     * Register Keystone's permission check with Laravel's Gate.
     *
     * Adds a single Gate::before callback that:
     * 1. allows super-admins every ability;
     * 2. allows an ability that names a permission the user holds;
     * 3. otherwise returns null, so policies and defined gates decide.
     *
     * Keystone never denies on the Gate's behalf, so a permission named like a
     * policy ability (in any tenant) cannot override that policy.
     *
     * Registering on the same Gate more than once has no further effect.
     */
    public function registerPermissions(Gate $gate): void
    {
        if (isset($this->registeredGates[$gate])) {
            return;
        }

        $this->registeredGates[$gate] = true;

        $gate->before(function ($user, $ability) {
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            // The cached name list keeps non-permission abilities (policy
            // methods, defined gates) from costing a query on every check.
            if (is_string($ability)
                && method_exists($user, 'hasPermissionTo')
                && $this->getAllPermissionNames()->contains($ability)
                && $user->hasPermissionTo($ability)) {
                return true;
            }

            return null;
        });
    }

    /**
     * Get all permissions (cached).
     *
     * @return Collection
     */
    protected function getPermissions()
    {
        $rows = $this->cache->remember(
            $this->cacheKey,
            $this->cacheExpiration(),
            fn () => KeystonePermission::withoutTenant()
                ->get(['name', 'guard_name'])
                ->map(fn ($permission) => [
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ])
                ->all()
        );

        return collect($rows);
    }

    /**
     * Forget the cached permissions.
     *
     * Call this after creating, updating, or deleting permissions.
     */
    public function forgetCachedPermissions(): void
    {
        $this->cache->forget($this->cacheKey);
    }

    /**
     * Forget the cached permission list and make sure the Gate callback is
     * registered on the given Gate.
     */
    public function refresh(Gate $gate): void
    {
        $this->forgetCachedPermissions();
        $this->registerPermissions($gate);
    }

    /**
     * Check if a permission exists in the system.
     */
    public function permissionExists(string $permissionName, ?string $guardName = null): bool
    {
        return $this->getPermissions()
            ->where('name', $permissionName)
            ->where('guard_name', DefaultGuard::resolve($guardName))
            ->isNotEmpty();
    }

    /**
     * Get all permission names.
     *
     * @return Collection
     */
    public function getAllPermissionNames()
    {
        return $this->getPermissions()->pluck('name');
    }
}
