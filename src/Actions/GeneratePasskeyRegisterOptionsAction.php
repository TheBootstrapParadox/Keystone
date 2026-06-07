<?php

namespace BSPDX\Keystone\Actions;

use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction as SpatieGeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Support\Serializer;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;

class GeneratePasskeyRegisterOptionsAction extends SpatieGeneratePasskeyRegisterOptionsAction
{
    public function execute(
        HasPasskeys $authenticatable,
        bool $asJson = true,
    ): string|PublicKeyCredentialCreationOptions {
        $options = new PublicKeyCredentialCreationOptions(
            rp: $this->relatedPartyEntity(),
            user: $this->generateUserEntity($authenticatable),
            challenge: $this->challenge(),
            authenticatorSelection: $this->authenticatorSelection(),
            attestation: config('keystone.passkey.attestation', 'none'),
            timeout: config('keystone.passkey.timeout', 60000),
        );

        if ($asJson) {
            return Serializer::make()->toJson($options);
        }

        return $options;
    }

    public function authenticatorSelection(): AuthenticatorSelectionCriteria
    {
        return new AuthenticatorSelectionCriteria(
            null,
            config('keystone.passkey.user_verification', 'preferred'),
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
        );
    }
}
