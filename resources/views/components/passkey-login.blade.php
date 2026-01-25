<div class="keystone-form-container">
  @include('keystone::components.keystone-styles')

    <div class="keystone-form">
        <h2 style="margin-bottom: 1.5rem; text-align: center; color: var(--text-primary);">
            Sign in with Passkey
        </h2>

        <button type="button" class="keystone-button" onclick="loginWithPasskey()">
            Sign in with Passkey
        </button>

        <div id="{{ $statusId }}" style="margin-top: 1rem; font-size: 0.875rem; text-align: center;"></div>

        <div class="keystone-links" style="justify-content: center;">
          <a href="{{ route('login') }}" class="keystone-link">Use password instead</a>
        </div>
    </div>
</div>

<script>
    async function loginWithPasskey() {
        const statusDiv = document.getElementById('{{ $statusId }}');

        try {
            statusDiv.innerHTML = '<p style="color: var(--primary);">Preparing passkey authentication...</p>';

            const optionsResponse = await fetch('{{ $loginOptionsUrl }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            if (!optionsResponse.ok) {
                throw new Error('Failed to get authentication options');
            }

            const options = await optionsResponse.json();
            const optionsJson = JSON.stringify(options);

            const publicKeyOptions = structuredClone(options);
            publicKeyOptions.challenge = Uint8Array.from(atob(publicKeyOptions.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));

            if (publicKeyOptions.allowCredentials) {
                publicKeyOptions.allowCredentials = publicKeyOptions.allowCredentials.map(cred => ({
                    ...cred,
                    id: Uint8Array.from(atob(cred.id.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)),
                }));
            }

            statusDiv.innerHTML = '<p style="color: var(--primary);">Follow your browser prompt...</p>';

            const credential = await navigator.credentials.get({ publicKey: publicKeyOptions });

            if (!credential) {
                throw new Error('Passkey authentication was cancelled');
            }

            statusDiv.innerHTML = '<p style="color: var(--primary);">Verifying...</p>';

            const response = await fetch('{{ $authenticateUrl }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    options: optionsJson,
                    credential: {
                        id: credential.id,
                        rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                        response: {
                            clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))),
                            authenticatorData: btoa(String.fromCharCode(...new Uint8Array(credential.response.authenticatorData))),
                            signature: btoa(String.fromCharCode(...new Uint8Array(credential.response.signature))),
                            userHandle: credential.response.userHandle ? btoa(String.fromCharCode(...new Uint8Array(credential.response.userHandle))) : null,
                        },
                        type: credential.type,
                    },
                }),
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Authentication failed');
            }

            statusDiv.innerHTML = '<p style="color: var(--secondary);">✓ Authentication successful! Redirecting...</p>';

          window.location.href = result.redirect || '{{ config('keystone.redirects.login', '/dashboard') }}';

        } catch (error) {
            console.error('Passkey authentication error:', error);
            statusDiv.innerHTML = `<p style="color: var(--secondary);">Error: ${error.message}</p>`;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Optionally auto-trigger passkey login
        // loginWithPasskey();
    });
</script>
