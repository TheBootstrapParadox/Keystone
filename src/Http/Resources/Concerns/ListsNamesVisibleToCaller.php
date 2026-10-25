<?php

namespace BSPDX\Keystone\Http\Resources\Concerns;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Support\MultiTenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @internal
 */
trait ListsNamesVisibleToCaller
{
    /**
     * Names of the related roles or permissions the caller may see.
     *
     * Relations are caller-independent, so a global row can hold rows from any
     * tenant. In multi-tenant mode, a caller with a tenant only sees names of
     * rows in their own tenant or global rows.
     *
     * @template TRow of KeystoneRole|KeystonePermission
     *
     * @param  Collection<int, TRow>  $related
     * @return list<string>
     */
    protected function namesVisibleToCaller(Request $request, Collection $related): array
    {
        $callerTenant = MultiTenancy::enabled() ? $request->user()?->getAttribute('tenant_id') : null;

        return $related
            ->when($callerTenant !== null, fn (Collection $rows) => $rows->filter(
                fn (KeystoneRole|KeystonePermission $row) => $row->tenant_id === null || $row->tenant_id === $callerTenant
            ))
            ->map(fn (KeystoneRole|KeystonePermission $row) => $row->name)
            ->values()
            ->all();
    }
}
