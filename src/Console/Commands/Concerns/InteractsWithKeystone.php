<?php

namespace BSPDX\Keystone\Console\Commands\Concerns;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithKeystone
{
    /**
     * Get the default guard name from config.
     */
    protected function getDefaultGuard(): string
    {
        return config('auth.defaults.guard', 'web');
    }

    /**
     * Resolve the guard from option or default.
     */
    protected function resolveGuard(): ?string
    {
        $guard = $this->option('guard');

        if ($guard && !array_key_exists($guard, config('auth.guards'))) {
            $this->error("Guard [{$guard}] is not defined in your auth configuration.");
            $this->info('Available guards: ' . implode(', ', array_keys(config('auth.guards'))));
            return null;
        }

        return $guard ?? $this->getDefaultGuard();
    }

    /**
     * Check if multi-tenant mode is enabled.
     */
    protected function isMultiTenantEnabled(): bool
    {
        return config('keystone.rbac.multi_tenant', false);
    }

    /**
     * Get the super admin role name from config.
     */
    protected function getSuperAdminRole(): string
    {
        return config('keystone.rbac.super_admin_role', 'super-admin');
    }

    /**
     * Get the default role from config.
     */
    protected function getDefaultRole(): ?string
    {
        return config('keystone.rbac.default_role');
    }

    /**
     * Clear the permission cache.
     */
    protected function clearPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Find a user by ID or email.
     */
    protected function findUser(string $identifier): ?User
    {
        if (is_numeric($identifier)) {
            return User::find($identifier);
        }

        return User::where('email', $identifier)->first();
    }
}
