<div class="keystone-form-container">
  @include('keystone::components.keystone-styles')
  <form method="POST" action="{{ $action }}" class="keystone-form" id="two-factor-form">
        @csrf

        <div id="code-input" style="display: block;">
            <div class="keystone-form-group">
              <label for="code" class="keystone-label">Authentication Code</label>
                <input
                    id="code"
                    type="text"
                    name="code"
                    inputmode="numeric"
                    autofocus
                    autocomplete="one-time-code"
                    class="keystone-input"
                    placeholder="123456"
                    maxlength="6"
                >
                <p style="font-size: 0.875rem; color: var(--keystone-text-muted); margin-top: 0.5rem;">
                    Enter the code from your authenticator app.
                </p>
                @error('code')
                  <span class="keystone-error">{{ $message }}</span>
                @enderror
            </div>
        </div>

        @if ($showRecoveryCodeOption)
          <div id="recovery-input" style="display: none;">
              <div class="keystone-form-group">
                <label for="recovery_code" class="keystone-label">Recovery Code</label>
                <input id="recovery_code" type="text" name="recovery_code" class="keystone-input" placeholder="xxxxx-xxxxx">
                <p style="font-size: 0.875rem; color: var(--keystone-text-muted); margin-top: 0.5rem;">
                  Enter one of your recovery codes.
                </p>
                @error('recovery_code')
                  <span class="keystone-error">{{ $message }}</span>
                @enderror
              </div>
          </div>
        @endif

        <button type="submit" class="keystone-button">
            Verify
        </button>

        @if ($showRecoveryCodeOption)
          <div style="text-align: center; margin-top: 1rem;">
              <button type="button" class="keystone-link"
                style="background: none; border: none; cursor: pointer; font-size: 0.875rem;" onclick="toggleRecoveryMode()">
                  <span id="toggle-text">Use a recovery code</span>
              </button>
          </div>
        @endif
    </form>
</div>

@if ($showRecoveryCodeOption)
<script>
    let usingRecoveryCode = false;

    function toggleRecoveryMode() {
        usingRecoveryCode = !usingRecoveryCode;

        const codeInput = document.getElementById('code-input');
        const recoveryInput = document.getElementById('recovery-input');
        const toggleText = document.getElementById('toggle-text');
        const codeField = document.getElementById('code');
        const recoveryField = document.getElementById('recovery_code');

        if (usingRecoveryCode) {
            codeInput.style.display = 'none';
            recoveryInput.style.display = 'block';
            toggleText.textContent = 'Use authenticator code';
            codeField.removeAttribute('required');
            recoveryField.setAttribute('required', 'required');
            recoveryField.focus();
        } else {
            codeInput.style.display = 'block';
            recoveryInput.style.display = 'none';
            toggleText.textContent = 'Use a recovery code';
            recoveryField.removeAttribute('required');
            codeField.setAttribute('required', 'required');
            codeField.focus();
        }
    }
</script>
@endif
