<?php

namespace BSPDX\Keystone\Services\Contracts;

interface CacheServiceInterface
{
    /**
     * Clear the permission cache.
     */
    public function clearPermissionCache(): void;

    /**
     * Forget cached permissions (alias for clearPermissionCache).
     */
    public function forgetCachedPermissions(): void;
}
