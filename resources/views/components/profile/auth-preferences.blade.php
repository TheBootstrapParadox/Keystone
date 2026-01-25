@props(['user', 'hasTwoFactor', 'hasPasskeys'])

<div class="keystone-auth-preferences">
  @include('keystone::components.keystone-styles')
    <style>
        .keystone-auth-preferences {
            /* Base styles */
        }

        .keystone-text {
            color: var(--keystone-text-muted, #6b7280);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .keystone-error {
            color: var(--keystone-danger, #dc2626);
            font-size: 0.875rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #fee2e2;
            border-radius: var(--keystone-radius, 0.5rem);
        }

        .keystone-checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .keystone-checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--keystone-bg-secondary, #f9fafb);
            border-radius: var(--keystone-radius, 0.5rem);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .keystone-checkbox-label:hover {
            background: #f3f4f6;
        }

        .keystone-checkbox-label.keystone-disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .keystone-checkbox-label.keystone-disabled:hover {
            background: var(--keystone-bg-secondary, #f9fafb);
        }

        .keystone-checkbox {
            margin-top: 0.25rem;
            width: 1rem;
            height: 1rem;
            cursor: pointer;
        }

        .keystone-checkbox:disabled {
            cursor: not-allowed;
        }

        .keystone-checkbox-content {
            display: flex;
            flex-direction: column;
        }

        .keystone-checkbox-title {
            font-weight: 500;
            color: var(--keystone-text, #1f2937);
        }

        .keystone-checkbox-description {
            font-size: 0.875rem;
            color: var(--keystone-text-muted, #6b7280);
        }

        .keystone-text-warning {
            color: #d97706;
            font-weight: 500;
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
    </style>

    <form method="POST" action="{{ route('keystone.profile.auth-preferences.update') }}">
        @csrf
        @method('PUT')

        <p class="keystone-text">
            Choose how you want to sign in to your account. You must have at least one authentication method enabled.
        </p>

        @error('auth_preferences')
          <div class="keystone-error">{{ $message }}</div>
        @enderror

        <div class="keystone-checkbox-group">
          <label class="keystone-checkbox-label">
                <input type="checkbox" name="require_password" value="1"
                    {{ $user->require_password ? 'checked' : '' }}
                    class="keystone-checkbox">
                <div class="keystone-checkbox-content">
                    <span class="keystone-checkbox-title">Require password</span>
                    <span class="keystone-checkbox-description">Use your password as the primary login method</span>
                </div>
            </label>

            <label class="keystone-checkbox-label {{ !$hasPasskeys ? 'keystone-disabled' : '' }}">
                <input type="checkbox" name="allow_passkey_login" value="1"
                    {{ $user->allow_passkey_login ? 'checked' : '' }}
                    {{ !$hasPasskeys ? 'disabled' : '' }}
                    class="keystone-checkbox">
                <div class="keystone-checkbox-content">
                    <span class="keystone-checkbox-title">Allow passkey login</span>
                    <span class="keystone-checkbox-description">
                        Sign in using biometrics or a security key instead of your password
                        @if(!$hasPasskeys)
                          <br><span class="keystone-text-warning">(Register a passkey first)</span>
                        @endif
                    </span>
                </div>
            </label>

            <label class="keystone-checkbox-label {{ !$hasTwoFactor ? 'keystone-disabled' : '' }}">
                <input type="checkbox" name="allow_totp_login" value="1"
                    {{ $user->allow_totp_login ? 'checked' : '' }}
                    {{ !$hasTwoFactor ? 'disabled' : '' }}
                    class="keystone-checkbox">
                <div class="keystone-checkbox-content">
                    <span class="keystone-checkbox-title">Allow authenticator code login</span>
                    <span class="keystone-checkbox-description">
                        Sign in using your authenticator app code instead of your password
                        @if(!$hasTwoFactor)
                          <br><span class="keystone-text-warning">(Enable 2FA first)</span>
                        @endif
                    </span>
                </div>
            </label>
        </div>

        <button type="submit" class="keystone-btn keystone-btn-primary">
            Save Preferences
        </button>
    </form>
</div>
