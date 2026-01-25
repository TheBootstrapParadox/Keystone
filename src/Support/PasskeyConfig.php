<?php

namespace BSPDX\Keystone\Support;

use Spatie\LaravelPasskeys\Support\Config as SpatieConfig;

/**
 * Configuration helper for passkey authentication.
 *
 * This class wraps Spatie's Config to provide an Keystone-native API
 * for accessing passkey configuration values.
 */
class PasskeyConfig
{
    /**
     * Get the configured authenticatable model class.
     *
     * @return class-string<\Illuminate\Contracts\Auth\Authenticatable>
     */
    public static function getAuthenticatableModel(): string
    {
        return SpatieConfig::getAuthenticatableModel();
    }

    /**
     * Get the configured passkey model class.
     *
     * @return class-string<\Spatie\LaravelPasskeys\Models\Passkey>
     */
    public static function getPasskeyModel(): string
    {
        return SpatieConfig::getPassKeyModel();
    }

    /**
     * Get the relying party name for WebAuthn.
     */
    public static function getRelyingPartyName(): string
    {
        return SpatieConfig::getRelyingPartyName();
    }

    /**
     * Get the relying party ID for WebAuthn.
     */
    public static function getRelyingPartyId(): string
    {
        return SpatieConfig::getRelyingPartyId();
    }

    /**
     * Get the relying party icon URL for WebAuthn.
     */
    public static function getRelyingPartyIcon(): ?string
    {
        return SpatieConfig::getRelyingPartyIcon();
    }

    /**
     * Get the redirect URL after successful passkey login.
     */
    public static function getRedirectAfterLogin(): ?string
    {
        return SpatieConfig::getRedirectAfterLogin();
    }
}
