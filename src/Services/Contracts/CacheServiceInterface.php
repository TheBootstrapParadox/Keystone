<?php

namespace BSPDX\Keystone\Services\Contracts;

interface CacheServiceInterface
{
    /**
     * Clear the permission cache.
     *
     * @return void
     */
    public function clearPermissionCache(): void;

    /**
     * Forget cached permissions (alias for clearPermissionCache).
     *
     * @return void
     */
    public function forgetCachedPermissions(): void;
}
