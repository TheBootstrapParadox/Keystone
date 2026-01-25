<div class="keystone-form-container">
  @include('keystone::components.keystone-styles')

    <div class="keystone-form">
      <div class="keystone-form-group">
        <label for="passkey-name" class="keystone-label">Passkey Name</label>
            <input
                id="passkey-name"
                type="text"
                class="keystone-input"
                placeholder="e.g., My iPhone, Work Laptop"
                value="My Passkey"
            >
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.5rem;">
                Give this passkey a recognizable name.
            </p>
        </div>

        <button type="button" class="keystone-button" onclick="registerPasskey()">
            Register Passkey
        </button>

        <div id="{{ $statusId }}" style="margin-top: 1rem; font-size: 0.875rem;"></div>
    </div>
</div>

<script>
    async function registerPasskey() {
        const nameInput = document.getElementById('passkey-name');
        const statusDiv = document.getElementById('{{ $statusId }}');
        const name = nameInput.value.trim() || 'My Passkey';

        try {
            statusDiv.innerHTML = '<p style="color: var(--primary);">Preparing passkey registration...</p>';

            const optionsResponse = await fetch('{{ $registerOptionsUrl }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            if (!optionsResponse.ok) {
                throw new Error('Failed to get registration options');
            }

            const options = await optionsResponse.json();
            const optionsJson = JSON.stringify(options);

            const publicKeyOptions = structuredClone(options);
            publicKeyOptions.challenge = Uint8Array.from(atob(publicKeyOptions.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
            publicKeyOptions.user.id = Uint8Array.from(atob(publicKeyOptions.user.id.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));

            statusDiv.innerHTML = '<p style="color: var(--primary);">Follow your browser prompt...</p>';

            const credential = await navigator.credentials.create({ publicKey: publicKeyOptions });

            if (!credential) {
                throw new Error('Passkey registration was cancelled');
            }

            statusDiv.innerHTML = '<p style="color: var(--primary);">Saving passkey...</p>';

            const response = await fetch('{{ $registerUrl }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    name: name,
                    options: optionsJson,
                    credential: {
                        id: credential.id,
                        rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                        response: {
                            clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))),
                            attestationObject: btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject))),
                        },
                        type: credential.type,
                    },
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to register passkey');
            }

            statusDiv.innerHTML = '<p style="color: var(--secondary);">✓ Passkey registered successfully!</p>';

            setTimeout(() => window.location.reload(), 1500);

        } catch (error) {
            console.error('Passkey registration error:', error);
            statusDiv.innerHTML = `<p style="color: var(--secondary);">Error: ${error.message}</p>`;
        }
    }
</script>
