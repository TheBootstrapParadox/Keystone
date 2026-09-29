<?php

namespace BSPDX\Keystone\Models\Concerns;

use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ResolvesByNameForTenant
{
    /**
     * Find a row by name as seen from the given tenant.
     *
     * Ignores the authenticated caller's tenant scope. In multi-tenant mode the
     * tenant's own row wins over a global row of the same name, and a null
     * tenant only matches global rows. In single-tenant mode, matches by name.
     *
     * @throws ModelNotFoundException
     */
    public static function findByNameForTenant(string $name, ?string $tenantId): static
    {
        $query = static::query()->withoutGlobalScope('tenant')->where('name', $name);

        if (config('keystone.features.multi_tenant', false)) {
            if ($tenantId === null) {
                $query->whereNull('tenant_id');
            } else {
                $query->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
                    ->orderByRaw('tenant_id IS NULL');
            }
        }

        return $query->orderBy('id')->firstOrFail();
    }

    /**
     * This model's tenant, used as the reference when resolving names for it.
     */
    protected function keystoneTenantId(): ?string
    {
        return config('keystone.features.multi_tenant', false) ? $this->tenant_id : null;
    }
}
