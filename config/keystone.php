<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Load Routes Automatically
    |--------------------------------------------------------------------------
    |
    | Determines whether Keystone should automatically load its routes.
    | Set to false to manually define routes in your application.
    |
    */

    'load_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | User Model Configuration
    |--------------------------------------------------------------------------
    |
    | Specify which User model to use. You can use your own User model
    | or use the built-in KeystoneUser model provided by the package.
    |
    */

    'user' => [
        // User model class to use
        // Default: null (uses config('auth.providers.users.model'))
        // Set to \BSPDX\Keystone\Models\KeystoneUser::class to use built-in User
        'model' => null,

        // Primary key type for KeystoneUser (if using built-in model)
        // Options: 'bigint' or 'uuid'
        'primary_key_type' => 'bigint',

        // Table name for KeystoneUser (if using built-in model)
        'table_name' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Features
    |--------------------------------------------------------------------------
    |
    | Toggle authentication features on/off. These control what functionality
    | is available in your application.
    |
    */

    'features' => [
        // Enable user registration
        'registration' => true,

        // Enable email verification
        'email_verification' => true,

        // Enable password reset functionality
        'password_reset' => true,

        // Enable two-factor authentication (TOTP via Fortify)
        'two_factor' => true,

        // Enable passkey authentication
        'passkeys' => true,

        // Enable passkey as a second factor
        'passkey_2fa' => true,

        // Enable API token authentication (Sanctum)
        'api_tokens' => true,

        // Enable profile information updates
        'update_profile' => true,

        // Enable password updates
        'update_passwords' => true,

        // Enable account deletion
        'account_deletion' => false,

        // Allow users to configure passwordless login options
        'passwordless_login' => true,

        // Show roles and permissions on profile page (requires Spatie Laravel Permission)
        'show_permissions' => true,

        // Enable multi-tenant mode (adds tenant_id to users table)
        'multi_tenant' => env('KEYSTONE_MULTI_TENANT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Role & Permission Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the RBAC system powered by Spatie Laravel Permission.
    |
    */

    'rbac' => [
        // Cache expiration time for roles and permissions (in seconds)
        'cache_expiration' => 60 * 60 * 24, // 24 hours

        // Default role assigned to new users (null = no default role)
        'default_role' => 'user',

        // Super admin role that bypasses all permission checks
        'super_admin_role' => 'super-admin',

        // Default permissions for API access
        'api_permissions' => [
            'view-roles',
            'view-permissions',
            'assign-roles',
            'assign-permissions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Passkey Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for WebAuthn/Passkey authentication.
    |
    */

    'passkey' => [
        // Relying Party name (your application name)
        'rp_name' => env('APP_NAME', 'Laravel'),

        // Relying Party ID (your domain)
        'rp_id' => env('APP_URL') ? parse_url(env('APP_URL'), PHP_URL_HOST) : 'localhost',

        // Timeout for passkey operations (in milliseconds)
        'timeout' => 60000,

        // User verification requirement: 'required', 'preferred', or 'discouraged'
        'user_verification' => 'preferred',

        // Attestation conveyance: 'none', 'indirect', or 'direct'
        'attestation' => 'none',

        // Allow users to have multiple passkeys
        'allow_multiple' => true,

        // Require passkey for specific user roles
        'required_for_roles' => [
            // 'admin',
            // 'super-admin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for TOTP-based 2FA (Google Authenticator, Authy, etc.)
    |
    */

    'two_factor' => [
        // QR code size (in pixels)
        'qr_code_size' => 200,

        // Number of recovery codes to generate
        'recovery_codes_count' => 8,

        // Window of time to accept TOTP codes (in periods, 1 period = 30 seconds)
        'window' => 1,

        // Require 2FA for specific user roles
        'required_for_roles' => [
            // 'admin',
            // 'super-admin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect Paths
    |--------------------------------------------------------------------------
    |
    | Define where users should be redirected after various auth actions.
    |
    */

    'redirects' => [
        'login' => '/dashboard',
        'logout' => '/',
        'register' => '/dashboard',
        'password_reset' => '/login',
        'email_verification' => '/dashboard',
        'two_factor_challenge' => '/dashboard',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for authentication attempts.
    |
    */

    'rate_limiting' => [
        // Maximum login attempts before lockout
        'max_login_attempts' => 5,

        // Lockout duration in minutes
        'lockout_duration' => 1,

        // Maximum 2FA attempts
        'max_2fa_attempts' => 3,

        // Maximum passkey attempts
        'max_passkey_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Settings
    |--------------------------------------------------------------------------
    |
    | Additional session configuration for enhanced security.
    |
    */

    'session' => [
        // Regenerate session ID after login
        'regenerate_on_login' => true,

        // Remember me duration (in minutes)
        'remember_duration' => 60 * 24 * 30, // 30 days

        // Require password confirmation for sensitive operations (in minutes)
        'password_timeout' => 10800, // 3 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile Page Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the user profile page.
    |
    */

    'profile' => [
        // The URI path where the profile page will be accessible
        'path' => '/profile',

        // Middleware to apply to profile routes
        'middleware' => ['web', 'auth'],

        // Require password confirmation before sensitive operations
        'require_password_confirm' => true,

        // Layout view to extend (set to null to use component-only mode)
        'layout' => 'layouts.app',
    ],

];
