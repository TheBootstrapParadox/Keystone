@extends(config('keystone.profile.layout', 'layouts.app'))

@section('content')
<div class="keystone-profile">
    @include('keystone::components.keystone-styles')
    <style>
        .keystone-profile {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .keystone-profile-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--keystone-text, #1f2937);
            margin-bottom: 2rem;
        }

        .keystone-profile-section {
            background: var(--keystone-bg, #ffffff);
            border-radius: var(--keystone-radius, 0.5rem);
            box-shadow: var(--keystone-shadow, 0 1px 3px 0 rgb(0 0 0 / 0.1));
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .keystone-profile-section h2 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--keystone-text, #1f2937);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--keystone-border, #e5e7eb);
        }

        .keystone-alert {
            padding: 1rem;
            border-radius: var(--keystone-radius, 0.5rem);
            margin-bottom: 1.5rem;
        }

        .keystone-alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .keystone-alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>

    <h1 class="keystone-profile-title">My Profile</h1>

    @if (session('status'))
        <div class="keystone-alert keystone-alert-success">
            @switch(session('status'))
                @case('auth-preferences-updated')
                    Your login preferences have been updated.
                    @break
                @case('passkey-registered')
                    Your passkey has been registered successfully.
                    @break
                @case('passkey-deleted')
                    Your passkey has been deleted.
                    @break
                @case('two-factor-enabled')
                    Two-factor authentication has been enabled.
                    @break
                @case('two-factor-disabled')
                    Two-factor authentication has been disabled.
                    @break
                @case('recovery-codes-regenerated')
                    Your recovery codes have been regenerated.
                    @break
                @default
                    {{ session('status') }}
            @endswitch
        </div>
    @endif

    @if ($errors->any())
        <div class="keystone-alert keystone-alert-error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Account Information --}}
    <section class="keystone-profile-section">
        <h2>Account Information</h2>
        @include('keystone::components.profile.account-info', ['user' => $user])
    </section>

    {{-- Roles & Permissions (if enabled) --}}
    @if(config('keystone.features.show_permissions') && isset($roles))
    <section class="keystone-profile-section">
        <h2>Roles & Permissions</h2>
        @include('keystone::components.profile.roles-permissions', ['roles' => $roles, 'permissions' => $permissions])
    </section>
    @endif

    {{-- Password Change --}}
    @if(config('keystone.features.update_passwords'))
    <section class="keystone-profile-section">
        <h2>Change Password</h2>
        @include('keystone::components.profile.password-form')
    </section>
    @endif

    {{-- Two-Factor Authentication --}}
    @if(config('keystone.features.two_factor'))
    <section class="keystone-profile-section">
        <h2>Two-Factor Authentication</h2>
        @include('keystone::components.profile.two-factor-management', ['enabled' => $hasTwoFactor])
    </section>
    @endif

    {{-- Passkeys --}}
    @if(config('keystone.features.passkeys'))
    <section class="keystone-profile-section">
        <h2>Passkeys</h2>
        @include('keystone::components.profile.passkey-management', ['passkeys' => $passkeys])
    </section>
    @endif

    {{-- Authentication Preferences --}}
    @if(config('keystone.features.passwordless_login'))
    <section class="keystone-profile-section">
        <h2>Login Preferences</h2>
        @include('keystone::components.profile.auth-preferences', [
            'user' => $user,
            'hasTwoFactor' => $hasTwoFactor,
            'hasPasskeys' => $hasPasskeys
        ])
    </section>
    @endif
</div>
@endsection
