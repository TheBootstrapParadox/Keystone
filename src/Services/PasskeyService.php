<?php

namespace BSPDX\Keystone\Services;

use BSPDX\Keystone\Services\Contracts\PasskeyServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Passkey;

class PasskeyService implements PasskeyServiceInterface
{
    public function __construct(
        protected GeneratePasskeyRegisterOptionsAction $generateRegisterOptionsAction,
        protected StorePasskeyAction $storePasskeyAction,
        protected GeneratePasskeyAuthenticationOptionsAction $generateAuthenticationOptionsAction,
        protected FindPasskeyToAuthenticateAction $findPasskeyToAuthenticateAction,
    ) {}

    /**
     * Generate passkey registration options for a user.
     */
    public function generateRegisterOptions(Authenticatable $user): string
    {
        /** @var HasPasskeys $user */
        return $this->generateRegisterOptionsAction->execute($user, asJson: true);
    }

    /**
     * Store a new passkey for the user.
     */
    public function storePasskey(
        Authenticatable $user,
        string $passkeyJson,
        string $optionsJson,
        array $additionalProperties = []
    ): Passkey {
        /** @var HasPasskeys $user */
        $hostname = parse_url(config('app.url'), PHP_URL_HOST);

        return $this->storePasskeyAction->execute(
            authenticatable: $user,
            passkeyJson: $passkeyJson,
            passkeyOptionsJson: $optionsJson,
            hostName: $hostname,
            additionalProperties: $additionalProperties,
        );
    }

    /**
     * Generate passkey authentication options.
     */
    public function generateAuthenticationOptions(): string
    {
        return $this->generateAuthenticationOptionsAction->execute();
    }

    /**
     * Find and validate a passkey for authentication.
     */
    public function findPasskeyToAuthenticate(string $credentialJson, string $optionsJson): ?Passkey
    {
        return $this->findPasskeyToAuthenticateAction->execute(
            publicKeyCredentialJson: $credentialJson,
            passkeyOptionsJson: $optionsJson,
        );
    }

    /**
     * Get the authenticatable user from a passkey.
     */
    public function getAuthenticatableFromPasskey(Passkey $passkey): Authenticatable
    {
        return $passkey->authenticatable;
    }
}
