<div class="keystone-password-form">
  @include('keystone::components.keystone-styles')
    <style>
        .keystone-password-form {
            /* Base styles */
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

        .keystone-input-error {
            border-color: var(--keystone-danger, #dc2626);
        }

        .keystone-error {
            color: var(--keystone-danger, #dc2626);
            font-size: 0.75rem;
            margin-top: 0.25rem;
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

    <form method="POST" action="{{ route('user-password.update') }}">
        @csrf
        @method('PUT')

        <div class="keystone-form-group">
          <label for="current_password" class="keystone-label">Current Password</label>
            <input type="password" name="current_password" id="current_password"
                class="keystone-input @error('current_password', 'updatePassword') keystone-input-error @enderror"
                required>
            @error('current_password', 'updatePassword')
              <span class="keystone-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="keystone-form-group">
          <label for="password" class="keystone-label">New Password</label>
            <input type="password" name="password" id="password"
                class="keystone-input @error('password', 'updatePassword') keystone-input-error @enderror"
                required>
            @error('password', 'updatePassword')
              <span class="keystone-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="keystone-form-group">
          <label for="password_confirmation" class="keystone-label">Confirm New Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                class="keystone-input" required>
        </div>

        <button type="submit" class="keystone-btn keystone-btn-primary">
            Update Password
        </button>
    </form>
</div>
