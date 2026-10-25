<?php

namespace BSPDX\Keystone\Support;

/**
 * Reads Keystone's multi-tenant setting.
 *
 * @internal
 */
final class MultiTenancy
{
    /**
     * Whether multi-tenancy is turned on (keystone.features.multi_tenant).
     */
    public static function enabled(): bool
    {
        return (bool) config('keystone.features.multi_tenant', false);
    }
}
