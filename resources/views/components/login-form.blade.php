<div class="keystone-form-container">
  @include('keystone::components.keystone-styles')

    <form method="POST" action="{{ $action }}" class="keystone-form">
        @csrf

        @if ($errors->any())
          <div class="keystone-error" style="margin-bottom: 1rem;">
              @foreach ($errors->all() as $error)
                  <p>{{ $error }}</p>
              @endforeach
          </div>
        @endif

        <div class="keystone-form-group">
          <label for="email" class="keystone-label">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                class="keystone-input"
            >
            @error('email')
              <span class="keystone-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="keystone-form-group">
          <label for="password" class="keystone-label">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                class="keystone-input"
            >
            @error('password')
              <span class="keystone-error">{{ $message }}</span>
            @enderror
        </div>

        @if ($showRememberMe)
          <div class="keystone-form-group">
            <label class="keystone-checkbox">
                  <input type="checkbox" name="remember" id="remember">
                  <span>Remember me</span>
              </label>
          </div>
        @endif

        <button type="submit" class="keystone-button">
            Log in
        </button>

        @if ($showPasskeyOption && config('keystone.features.passkeys'))
          <div class="keystone-divider">or</div>
          <button type="button" class="keystone-button keystone-button-secondary" onclick="loginWithPasskey()">
                  Log in with Passkey
              </button>
        @endif

        <div class="keystone-links">
            @if ($showForgotPassword)
              <a href="{{ route('password.request') }}" class="keystone-link">Forgot password?</a>
            @endif
            @if ($showRegisterLink && config('keystone.features.registration'))
              <a href="{{ route('register') }}" class="keystone-link">Create account</a>
            @endif
        </div>
    </form>
</div>

@if ($showPasskeyOption && config('keystone.features.passkeys'))
<script>
    async function loginWithPasskey() {
        // Redirect to passkey login page or trigger passkey auth
        window.location.href = '{{ route('passkeys.login') }}';
    }
</script>
@endif
