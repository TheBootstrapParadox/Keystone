<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Models\KeystonePermission;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

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
    protected string $cacheKey = 'keystone.permissions.all';

    public function __construct(CacheRepository $cache)
    {
        $this->cache = $cache;
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
     * Register all permissions with Laravel's Gate.
     *
     * This method:
     * 1. Adds a Gate::before callback for super-admin bypass
     * 2. Defines a gate for each permission in the system
     */
    public function registerPermissions(Gate $gate): void
    {
        // Register a single Gate::before callback to handle ALL permission checks
        $gate->before(function ($user, $ability) {
            // Super-admin bypass - check if user has isSuperAdmin method and it returns true
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            // Check if the ability corresponds to a permission name
            // This handles permissions created dynamically (e.g., in tests)
            if (method_exists($user, 'hasPermissionTo')) {
                // Try to find the permission by name (check without tenant scope)
                $permission = KeystonePermission::withoutTenant()->where('name', $ability)->first();

                if ($permission) {
                    // Permission exists - check if user has it
                    // Return true if they have it, false if they don't
                    // This prevents the gate from continuing to check policies
                    return $user->hasPermissionTo($permission) ? true : false;
                }
            }

            // Allow gate to continue checking (will check policies, etc.)
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
        return $this->cache->remember(
            $this->cacheKey,
            $this->cacheExpiration(),
            function () {
                return KeystonePermission::withoutTenant()->get();
            }
        );
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
     * Re-register all permissions with the gate.
     *
     * Useful after permission changes.
     */
    public function refresh(Gate $gate): void
    {
        $this->forgetCachedPermissions();
        $this->registerPermissions($gate);
    }

    /**
     * Check if a permission exists in the system.
     */
    public function permissionExists(string $permissionName, string $guardName = 'web'): bool
    {
        return $this->getPermissions()
            ->where('name', $permissionName)
            ->where('guard_name', $guardName)
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
