<?php

namespace BSPDX\Keystone\Console\Commands\Concerns;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * For commands with a --tenant option: validate it and look up roles in it,
 * and the permissions given for those roles.
 */
trait ResolvesTenantOption
{
    /**
     * The validated --tenant option: a tenant UUID, null when not given, or
     * false when invalid (the error has already been printed).
     */
    protected function resolveTenant(): string|false|null
    {
        $tenant = $this->option('tenant');

        if ($tenant === null || $tenant === '') {
            return null;
        }

        if (! $this->isMultiTenantEnabled()) {
            $this->error('Multi-tenancy is not enabled; --tenant cannot be used.');

            return false;
        }

        if (! Str::isUuid($tenant)) {
            $this->error("Tenant [{$tenant}] is not a valid UUID.");

            return false;
        }

        return $tenant;
    }

    /**
     * Find a role by name on the guard, as seen from the tenant (global rows
     * only when the tenant is null in multi-tenant mode).
     */
    protected function findRole(string $name, string $guard, ?string $tenant): ?KeystoneRole
    {
        try {
            return KeystoneRole::findByNameForTenant($name, $tenant, $guard);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Resolve permission names for a role on the role's guard (which may not
     * be the default guard) and in the role's tenant.
     *
     * @param  array<int, string>  $names
     * @return list<KeystonePermission>
     *
     * @throws InvalidArgumentException when a name does not resolve
     */
    protected function findPermissionsForRole(KeystoneRole $role, array $names): array
    {
        $tenant = $this->isMultiTenantEnabled() ? $role->tenant_id : null;

        return array_values(array_map(function (string $name) use ($role, $tenant) {
            try {
                return KeystonePermission::findByNameForTenant($name, $tenant, $role->guard_name);
            } catch (ModelNotFoundException) {
                throw new InvalidArgumentException("Permission '{$name}' not found for guard '{$role->guard_name}'.");
            }
        }, $names));
    }

    /**
     * The error for a role that could not be found.
     */
    protected function roleNotFoundMessage(string $name, string $guard, ?string $tenant): string
    {
        return "Role '{$name}' not found for guard '{$guard}'".($tenant !== null ? " in tenant '{$tenant}'." : '.');
    }
}
