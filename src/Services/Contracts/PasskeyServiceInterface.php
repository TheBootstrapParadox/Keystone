<?php

namespace BSPDX\Keystone\Services\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\LaravelPasskeys\Models\Passkey;

interface PasskeyServiceInterface
{
    /**
     * Generate passkey registration options for a user.
     *
     * @param Authenticatable $user
     * @return string JSON string of registration options
     */
    public function generateRegisterOptions(Authenticatable $user): string;

    /**
     * Store a new passkey for the user.
     *
     * @param Authenticatable $user
     * @param string $passkeyJson The passkey credential JSON from the browser
     * @param string $optionsJson The registration options JSON that was used
     * @param array $additionalProperties Optional additional properties (e.g., 'name')
     * @return Passkey
     */
    public function storePasskey(
        Authenticatable $user,
        string $passkeyJson,
        string $optionsJson,
        array $additionalProperties = []
    ): Passkey;

    /**
     * Generate passkey authentication options.
     *
     * @return string JSON string of authentication options
     */
    public function generateAuthenticationOptions(): string;

    /**
     * Find and validate a passkey for authentication.
     *
     * @param string $credentialJson The credential JSON from the browser
     * @param string $optionsJson The authentication options JSON that was used
     * @return Passkey|null
     */
    public function findPasskeyToAuthenticate(string $credentialJson, string $optionsJson): ?Passkey;

    /**
     * Get the authenticatable user from a passkey.
     *
     * @param Passkey $passkey
     * @return Authenticatable
     */
    public function getAuthenticatableFromPasskey(Passkey $passkey): Authenticatable;
}
