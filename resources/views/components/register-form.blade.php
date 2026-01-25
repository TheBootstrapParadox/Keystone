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

        @if (in_array('name', $requiredFields))
          <div class="keystone-form-group">
            <label for="name" class="keystone-label">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus class="keystone-input">
            @error('name')
              <span class="keystone-error">{{ $message }}</span>
            @enderror
          </div>
        @endif

        @if (in_array('email', $requiredFields))
          <div class="keystone-form-group">
            <label for="email" class="keystone-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required class="keystone-input">
            @error('email')
              <span class="keystone-error">{{ $message }}</span>
            @enderror
          </div>
        @endif

        @if (in_array('password', $requiredFields))
          <div class="keystone-form-group">
            <label for="password" class="keystone-label">Password</label>
            <input id="password" type="password" name="password" required class="keystone-input">
            @error('password')
              <span class="keystone-error">{{ $message }}</span>
            @enderror
          </div>
        @endif

        @if (in_array('password_confirmation', $requiredFields))
          <div class="keystone-form-group">
            <label for="password_confirmation" class="keystone-label">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required class="keystone-input"
              >
          </div>
        @endif

        <button type="submit" class="keystone-button">
            Register
        </button>

        @if ($showLoginLink)
          <div class="keystone-links">
            <a href="{{ route('login') }}" class="keystone-link">Already have an account? Log in</a>
          </div>
        @endif
    </form>
</div>
