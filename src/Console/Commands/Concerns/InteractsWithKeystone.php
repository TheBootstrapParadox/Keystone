<?php

namespace BSPDX\Keystone\Console\Commands\Concerns;

use BSPDX\Keystone\Services\Contracts\CacheServiceInterface;
use BSPDX\Keystone\Support\DefaultGuard;
use BSPDX\Keystone\Support\MultiTenancy;
use BSPDX\Keystone\Support\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait InteractsWithKeystone
{
    /**
     * Get the User model class.
     */
    protected function getUserModel(): string
    {
        return UserModel::className();
    }

    /**
     * Get the default guard name from config.
     */
    protected function getDefaultGuard(): string
    {
        return DefaultGuard::resolve();
    }

    /**
     * Resolve the guard from option or default.
     */
    protected function resolveGuard(): ?string
    {
        // Not every command using this trait defines --guard
        $guard = $this->hasOption('guard') ? $this->input->getOption('guard') : null;

        if ($guard && ! array_key_exists($guard, config('auth.guards'))) {
            $this->error("Guard [{$guard}] is not defined in your auth configuration.");
            $this->info('Available guards: '.implode(', ', array_keys(config('auth.guards'))));

            return null;
        }

        return $guard ?? $this->getDefaultGuard();
    }

    /**
     * Check if multi-tenant mode is enabled.
     */
    protected function isMultiTenantEnabled(): bool
    {
        return MultiTenancy::enabled();
    }

    /**
     * Get the super admin role name from config.
     */
    protected function getSuperAdminRole(): string
    {
        return config('keystone.rbac.super_admin_role', 'super-admin');
    }

    /**
     * Clear the permission cache.
     */
    protected function clearPermissionCache(): void
    {
        app(CacheServiceInterface::class)->clearPermissionCache();
    }

    /**
     * Find a user by primary key or email.
     *
     * The identifier is only tried as a key when it has the key's shape
     * (digits, UUID, or ULID), so an email is never compared against an
     * integer column (which PostgreSQL rejects).
     */
    protected function findUser(string $identifier)
    {
        $userClass = $this->getUserModel();

        if ($this->looksLikeUserKey(new $userClass, $identifier)
            && $user = $userClass::find($identifier)) {
            return $user;
        }

        return $userClass::where('email', $identifier)->first();
    }

    /**
     * Whether the identifier could be a primary key of the given user model.
     */
    protected function looksLikeUserKey(Model $user, string $identifier): bool
    {
        $traits = class_uses_recursive($user);

        if (in_array(HasUuids::class, $traits, true)) {
            return Str::isUuid($identifier);
        }

        if (in_array(HasUlids::class, $traits, true)) {
            return Str::isUlid($identifier);
        }

        if (in_array($user->getKeyType(), ['int', 'integer'], true)) {
            return ctype_digit($identifier);
        }

        return true;
    }
}
