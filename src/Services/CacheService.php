<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;

class CacheService implements CacheServiceInterface
{
    /**
     * The Keystone permission registrar instance.
     */
    protected PermissionRegistrar $registrar;

    /**
     * Create a new cache service instance.
     */
    public function __construct(PermissionRegistrar $registrar)
    {
        $this->registrar = $registrar;
    }

    /**
     * Clear the permission cache.
     */
    public function clearPermissionCache(): void
    {
        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Forget cached permissions (alias for clearPermissionCache).
     */
    public function forgetCachedPermissions(): void
    {
        $this->clearPermissionCache();
    }
}
