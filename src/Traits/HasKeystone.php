<?php

namespace BSPDX\Keystone\Traits;

use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

trait HasKeystone
{
    use HasApiTokens;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use InteractsWithPasskeys;

    /**
     * Determine if the user has enabled two-factor authentication.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_secret) &&
               !is_null($this->two_factor_confirmed_at);
    }

    /**
     * Determine if the user has registered any passkeys.
     */
    public function hasPasskeysRegistered(): bool
    {
        return $this->passkeys()->exists();
    }

    /**
     * Determine if 2FA is required for this user based on their roles.
     */
    public function requires2FA(): bool
    {
        $requiredRoles = config('keystone.two_factor.required_for_roles', []);

        if (empty($requiredRoles)) {
            return false;
        }

        return $this->hasAnyRole($requiredRoles);
    }

    /**
     * Determine if passkeys are required for this user based on their roles.
     */
    public function requiresPasskey(): bool
    {
        $requiredRoles = config('keystone.passkey.required_for_roles', []);

        if (empty($requiredRoles)) {
            return false;
        }

        return $this->hasAnyRole($requiredRoles);
    }

    /**
     * Get the user's authentication methods.
     */
    public function getAuthenticationMethods(): array
    {
        return [
            'password' => true,
            'two_factor' => $this->hasTwoFactorEnabled(),
            'passkey' => $this->hasPasskeysRegistered(),
        ];
    }

    /**
     * Determine if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        $superAdminRole = config('keystone.rbac.super_admin_role', 'super-admin');

        return $this->hasRole($superAdminRole);
    }

    /**
     * Check if user can bypass permission checks (super admin).
     */
    public function canBypassPermissions(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Check if user can use passwordless login.
     */
    public function canUsePasswordlessLogin(): bool
    {
        return ($this->allow_passkey_login && $this->hasPasskeysRegistered()) ||
               ($this->allow_totp_login && $this->hasTwoFactorEnabled());
    }

    /**
     * Get available authentication methods for this user.
     */
    public function getAvailableAuthMethods(): array
    {
        $methods = [];

        if ($this->require_password) {
            $methods[] = 'password';
        }

        if ($this->allow_passkey_login && $this->hasPasskeysRegistered()) {
            $methods[] = 'passkey';
        }

        if ($this->allow_totp_login && $this->hasTwoFactorEnabled()) {
            $methods[] = 'totp';
        }

        return $methods;
    }

    /**
     * Validate that at least one auth method is enabled.
     */
    public function hasValidAuthConfiguration(): bool
    {
        return $this->require_password ||
               ($this->allow_passkey_login && $this->hasPasskeysRegistered()) ||
               ($this->allow_totp_login && $this->hasTwoFactorEnabled());
    }

    /**
     * Get the auth preference fillable attributes.
     */
    public static function getAuthPreferenceFillable(): array
    {
        return [
            'allow_passkey_login',
            'allow_totp_login',
            'require_password',
        ];
    }

    /**
     * Get the auth preference cast attributes.
     */
    public static function getAuthPreferenceCasts(): array
    {
        return [
            'allow_passkey_login' => 'boolean',
            'allow_totp_login' => 'boolean',
            'require_password' => 'boolean',
        ];
    }
}
