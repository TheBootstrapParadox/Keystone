<?php

namespace BSPDX\Keystone\Models\Concerns;

use BSPDX\Keystone\Support\DefaultGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

trait ResolvesByNameForTenant
{
    /**
     * Find a row by name as seen from the given tenant.
     *
     * Ignores the authenticated caller's tenant scope and only matches rows on
     * the given guard (the default auth guard when null). In multi-tenant mode
     * the tenant's own row wins over a global row of the same name, and a null
     * tenant only matches global rows. In single-tenant mode, matches by name.
     *
     * @throws ModelNotFoundException
     */
    public static function findByNameForTenant(string $name, ?string $tenantId, ?string $guard = null): static
    {
        $query = static::query()
            ->withoutGlobalScope('tenant')
            ->where('name', $name)
            ->where('guard_name', DefaultGuard::resolve($guard));

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
     * Resolve a model instance, integer ID, or name to a model.
     *
     * Instances and IDs are used as given (IDs ignore the tenant scope); names
     * resolve in the given tenant via findByNameForTenant().
     *
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     */
    public static function resolveForTenant(mixed $value, ?string $tenantId): static
    {
        if ($value instanceof static) {
            return $value;
        }

        if (is_int($value)) {
            return static::query()->withoutGlobalScope('tenant')->findOrFail($value);
        }

        if (is_string($value)) {
            return static::findByNameForTenant($value, $tenantId);
        }

        throw new InvalidArgumentException('Expected a '.static::class.' instance, an integer ID, or a name.');
    }

    /**
     * Whether another row has this row's name, guard, and tenant.
     *
     * Unique keys treat NULL tenants as distinct, so in multi-tenant mode this
     * is how duplicate global rows are caught. Single-tenant mode relies on the
     * database's (name, guard_name) unique key.
     */
    protected function duplicatesAnotherRow(): bool
    {
        if (! config('keystone.features.multi_tenant', false)
            || ($this->exists && ! $this->isDirty(['name', 'guard_name', 'tenant_id']))) {
            return false;
        }

        return static::query()
            ->withoutGlobalScope('tenant')
            ->where('name', $this->name)
            ->where('guard_name', $this->guard_name)
            ->when(
                $this->tenant_id === null,
                fn ($query) => $query->whereNull('tenant_id'),
                fn ($query) => $query->where('tenant_id', $this->tenant_id)
            )
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }

    /**
     * This model's tenant, used as the reference when resolving names for it.
     */
    protected function keystoneTenantId(): ?string
    {
        return config('keystone.features.multi_tenant', false) ? $this->tenant_id : null;
    }
}
