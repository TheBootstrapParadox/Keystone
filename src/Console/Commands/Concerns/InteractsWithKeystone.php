<?php

namespace BSPDX\Keystone\Console\Commands\Concerns;

use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;

trait InteractsWithKeystone
{
    /**
     * Get the User model class.
     */
    protected function getUserModel(): string
    {
        return config('keystone.user.model')
            ?? config('auth.providers.users.model', \App\Models\User::class);
    }
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
        return config('keystone.features.multi_tenant', false);
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
        app(CacheServiceInterface::class)->clearPermissionCache();
    }

    /**
     * Find a user by ID or email.
     */
    protected function findUser(string $identifier)
    {
        $userClass = $this->getUserModel();

        if (is_numeric($identifier)) {
            return $userClass::find($identifier);
        }

        return $userClass::where('email', $identifier)->first();
    }
}
