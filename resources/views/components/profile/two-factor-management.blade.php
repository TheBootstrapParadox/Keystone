@props(['enabled' => false])

<div class="keystone-two-factor">
  @include('keystone::components.keystone-styles')
    <style>
        .keystone-two-factor {
            /* Base styles */
        }

        .keystone-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border-radius: var(--keystone-radius, 0.5rem);
            margin-bottom: 1rem;
        }

        .keystone-status-success {
            background: #d1fae5;
            color: #065f46;
        }

        .keystone-icon {
            width: 1.25rem;
            height: 1.25rem;
        }

        .keystone-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .keystone-text {
            color: var(--keystone-text-muted, #6b7280);
            font-size: 0.875rem;
            margin-bottom: 1rem;
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

        .keystone-btn-secondary {
            background: var(--keystone-bg-secondary, #f3f4f6);
            color: var(--keystone-text, #1f2937);
        }

        .keystone-btn-secondary:hover {
            background: #e5e7eb;
        }

        .keystone-btn-danger {
            background: var(--keystone-danger, #dc2626);
            color: white;
        }

        .keystone-btn-danger:hover {
            background: #b91c1c;
        }

        .keystone-inline {
            display: inline;
        }

        .keystone-recovery-codes-container {
            margin-top: 1rem;
            display: none;
        }

        .keystone-recovery-codes-container.show {
            display: block;
        }

        .keystone-setup-container {
            margin-top: 1rem;
            display: none;
        }

        .keystone-setup-container.show {
            display: block;
        }
    </style>

    @if($enabled)
      <div class="keystone-status keystone-status-success">
        <svg class="keystone-icon" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd" />
        </svg>
        <span>Two-factor authentication is enabled</span>
      </div>

      <div class="keystone-actions">
        <button type="button" class="keystone-btn keystone-btn-secondary" onclick="toggleRecoveryCodes()">
          <span id="toggle-codes-text">View Recovery Codes</span>
        </button>

        <form method="POST" action="{{ route('two-factor.destroy') }}" class="keystone-inline">
          @csrf
          @method('DELETE')
          <button type="submit" class="keystone-btn keystone-btn-danger"
            onclick="return confirm('Are you sure you want to disable two-factor authentication?')">
            Disable 2FA
          </button>
        </form>
      </div>

      <div id="recovery-codes-container" class="keystone-recovery-codes-container">
        @include('keystone::components.profile.recovery-codes')
      </div>
    @else
      <p class="keystone-text">
        Add additional security to your account using two-factor authentication.
        When enabled, you'll be prompted for a secure, random code during login.
      </p>

      <button type="button" class="keystone-btn keystone-btn-primary" onclick="toggleSetup()">
        Enable Two-Factor Authentication
      </button>

      <div id="setup-container" class="keystone-setup-container">
        @include('keystone::components.profile.two-factor-setup')
      </div>
    @endif
</div>

<script>
    function toggleRecoveryCodes() {
        const container = document.getElementById('recovery-codes-container');
        const toggleText = document.getElementById('toggle-codes-text');

        if (container.classList.contains('show')) {
            container.classList.remove('show');
            toggleText.textContent = 'View Recovery Codes';
        } else {
            container.classList.add('show');
            toggleText.textContent = 'Hide Recovery Codes';
        }
    }

    function toggleSetup() {
        const container = document.getElementById('setup-container');
        container.classList.toggle('show');
    }
</script>
