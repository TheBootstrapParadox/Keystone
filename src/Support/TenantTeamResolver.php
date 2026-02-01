<?php

namespace BSPDX\Keystone\Support;

use Spatie\Permission\Contracts\TeamResolver;

class TenantTeamResolver implements TeamResolver
{
    /**
     * Get the team ID for the current context.
     *
     * Returns the authenticated user's tenant_id when multi-tenancy is enabled,
     * otherwise returns null for non-tenant installations.
     *
     * @return string|int|null
     */
    public function getTeamId(): string|int|null
    {
        // Only return tenant_id if multi-tenancy is enabled
        if (!config('keystone.features.multi_tenant', false)) {
            return null;
        }

        // Return the authenticated user's tenant_id
        return auth()->check() ? auth()->user()->tenant_id : null;
    }
}
