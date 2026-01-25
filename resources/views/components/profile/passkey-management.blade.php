@props(['passkeys'])

<div class="keystone-passkeys">
  @include('keystone::components.keystone-styles')
    <style>
        .keystone-passkeys {
            /* Base styles */
        }

        .keystone-passkey-list {
            margin-bottom: 1.5rem;
        }

        .keystone-subsection-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--keystone-text-muted, #6b7280);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .keystone-passkey-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--keystone-bg-secondary, #f9fafb);
            border-radius: var(--keystone-radius, 0.5rem);
            margin-bottom: 0.5rem;
        }

        .keystone-passkey-info {
            display: flex;
            flex-direction: column;
        }

        .keystone-passkey-name {
            font-weight: 500;
            color: var(--keystone-text, #1f2937);
        }

        .keystone-passkey-meta {
            font-size: 0.75rem;
            color: var(--keystone-text-muted, #6b7280);
        }

        .keystone-passkey-register {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--keystone-border, #e5e7eb);
        }

        .keystone-text {
            color: var(--keystone-text-muted, #6b7280);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .keystone-form-group {
            margin-bottom: 1rem;
        }

        .keystone-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--keystone-text, #1f2937);
            font-size: 0.875rem;
        }

        .keystone-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--keystone-border, #d1d5db);
            border-radius: var(--keystone-radius, 0.5rem);
            font-size: 0.875rem;
        }

        .keystone-input:focus {
            outline: none;
            border-color: var(--keystone-primary, #4f46e5);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .keystone-btn {
            padding: 0.5rem 1rem;
            border-radius: var(--keystone-radius, 0.5rem);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: background-color 0.2s;
        }

        .keystone-btn-primary {
            background: var(--keystone-primary, #4f46e5);
            color: white;
        }

        .keystone-btn-primary:hover {
            background: var(--keystone-primary-hover, #4338ca);
        }

        .keystone-btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .keystone-btn-danger {
            background: var(--keystone-danger, #dc2626);
            color: white;
        }

        .keystone-btn-danger:hover {
            background: #b91c1c;
        }

        .keystone-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .keystone-message {
            margin-top: 0.75rem;
            font-size: 0.875rem;
        }

        .keystone-success {
            color: #059669;
        }

        .keystone-error {
            color: var(--keystone-danger, #dc2626);
        }

        .keystone-inline {
            display: inline;
        }

        .keystone-no-passkeys {
            color: var(--keystone-text-muted, #6b7280);
            font-size: 0.875rem;
            font-style: italic;
        }
    </style>

    {{-- Existing Passkeys --}}
    @if($passkeys->count() > 0)
      <div class="keystone-passkey-list">
        <h3 class="keystone-subsection-title">Your Passkeys</h3>

        @foreach($passkeys as $passkey)
          <div class="keystone-passkey-item">
            <div class="keystone-passkey-info">
              <span class="keystone-passkey-name">{{ $passkey->name }}</span>
              <span class="keystone-passkey-meta">
                Added {{ $passkey->created_at->format('M j, Y') }}
                @if($passkey->last_used_at)
                  &middot; Last used {{ $passkey->last_used_at->diffForHumans() }}
                @endif
              </span>
            </div>
            <form method="POST" action="{{ route('passkeys.destroy', $passkey->id) }}" class="keystone-inline">
              @csrf
              @method('DELETE')
              <button type="submit" class="keystone-btn keystone-btn-sm keystone-btn-danger"
                            onclick="return confirm('Are you sure you want to delete this passkey?')">
                            Delete
                        </button>
                    </form>
                </div>
        @endforeach
          </div>
    @else
      <p class="keystone-no-passkeys">You haven't registered any passkeys yet.</p>
    @endif

    {{-- Register New Passkey --}}
    <div class="keystone-passkey-register">
      <h3 class="keystone-subsection-title">Add a New Passkey</h3>

        <p class="keystone-text">
            Passkeys let you sign in using your fingerprint, face, or device PIN.
        </p>

        <div class="keystone-form-group">
          <label class="keystone-label" for="passkey-name">Passkey Name</label>
          <input type="text" id="passkey-name" placeholder="e.g., MacBook Pro, iPhone, YubiKey" class="keystone-input">
        </div>

        <button type="button" onclick="registerPasskeyFromProfile()" id="register-passkey-btn"
            class="keystone-btn keystone-btn-primary">
            Register Passkey
        </button>

        <div id="passkey-status" class="keystone-message"></div>
    </div>
</div>

<script>
    async function registerPasskeyFromProfile() {
        const nameInput = document.getElementById('passkey-name');
        const statusEl = document.getElementById('passkey-status');
        const btn = document.getElementById('register-passkey-btn');
        const name = nameInput.value.trim() || 'My Passkey';

        if (!name) {
          statusEl.innerHTML = '<span class="keystone-error">Please enter a name for your passkey.</span>';
            return;
        }

        if (!window.PublicKeyCredential) {
          statusEl.innerHTML = '<span class="keystone-error">Passkeys are not supported in this browser.</span>';
            return;
        }

        btn.disabled = true;
        statusEl.innerHTML = '<span>Preparing passkey registration...</span>';

        try {
            // Get registration options from server
            const optionsResponse = await fetch('{{ route("passkeys.register.options") }}', {
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

            // Store original options to send back to server for validation
            const originalOptions = JSON.parse(JSON.stringify(options));

            // Prepare options for WebAuthn
            options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
            options.user.id = Uint8Array.from(atob(options.user.id.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));

            statusEl.innerHTML = '<span>Follow your browser prompt...</span>';

            // Create credential
            const credential = await navigator.credentials.create({ publicKey: options });

            if (!credential) {
                throw new Error('Passkey registration was cancelled');
            }

            statusEl.innerHTML = '<span>Saving passkey...</span>';

            // Send credential to server
            const response = await fetch('{{ route("passkeys.register") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    name: name,
                    credential: {
                        id: credential.id,
                        rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                        response: {
                            clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))),
                            attestationObject: btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject))),
                        },
                        type: credential.type,
                    },
                    options: originalOptions,
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to register passkey');
            }

          statusEl.innerHTML = '<span class="keystone-success">Passkey registered successfully!</span>';

            // Reload page after a short delay
            setTimeout(() => window.location.reload(), 1500);

        } catch (error) {
            console.error('Passkey registration error:', error);
            if (error.name === 'NotAllowedError') {
              statusEl.innerHTML = '<span class="keystone-error">Registration was cancelled or timed out.</span>';
            } else {
              statusEl.innerHTML = `<span class="keystone-error">Error: ${error.message}</span>`;
            }
            btn.disabled = false;
        }
    }
</script>
