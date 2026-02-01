<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;
use Spatie\Permission\PermissionRegistrar;

class CacheService implements CacheServiceInterface
{
    /**
     * The Spatie permission registrar instance.
     *
     * @var PermissionRegistrar
     */
    protected PermissionRegistrar $registrar;

    /**
     * Create a new cache service instance.
     *
     * @param PermissionRegistrar $registrar
     */
    public function __construct(PermissionRegistrar $registrar)
    {
        $this->registrar = $registrar;
    }

    /**
     * Clear the permission cache.
     *
     * @return void
     */
    public function clearPermissionCache(): void
    {
        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Forget cached permissions (alias for clearPermissionCache).
     *
     * @return void
     */
    public function forgetCachedPermissions(): void
    {
        $this->clearPermissionCache();
    }
}
