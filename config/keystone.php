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
        'model' => \BSPDX\Keystone\Models\KeystoneUser::class,

        // Primary key type for KeystoneUser (if using built-in model)
        // Options: 'bigint' or 'uuid'
        'primary_key_type' => 'uuid',

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

        // Show roles and permissions on profile page
        'show_permissions' => true,

        // Enable multi-tenant mode (adds tenant_id to users table)
        'multi_tenant' => env('KEYSTONE_MULTI_TENANT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Role & Permission Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the role-based access control (RBAC) system.
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
    | Permission System Configuration
    |--------------------------------------------------------------------------
    |
    | Full configuration for the permission system, embedded here so
    | Keystone has a single source of truth for RBAC settings.
    |
    */

    'permission' => [
        'models' => [

            /*
             * The Eloquent model used to retrieve permissions.
             */

            'permission' => BSPDX\Keystone\Models\KeystonePermission::class,

            /*
             * The Eloquent model used to retrieve roles.
             */

            'role' => BSPDX\Keystone\Models\KeystoneRole::class,

        ],

        'table_names' => [

            /*
             * When using the "HasRoles" trait from this package, we need to know which
             * table should be used to retrieve your roles. We have chosen a basic
             * default value but you may easily change it to any table you like.
             */

            'roles' => 'roles',

            /*
             * When using the "HasPermissions" trait from this package, we need to know which
             * table should be used to retrieve your permissions. We have chosen a basic
             * default value but you may easily change it to any table you like.
             */

            'permissions' => 'permissions',

            /*
             * When using the "HasPermissions" trait from this package, we need to know which
             * table should be used to retrieve your models permissions. We have chosen a
             * basic default value but you may easily change it to any table you like.
             */

            'model_has_permissions' => 'model_has_permissions',

            /*
             * When using the "HasRoles" trait from this package, we need to know which
             * table should be used to retrieve your models roles. We have chosen a
             * basic default value but you may easily change it to any table you like.
             */

            'model_has_roles' => 'model_has_roles',

            /*
             * When using the "HasRoles" trait from this package, we need to know which
             * table should be used to retrieve your roles permissions. We have chosen a
             * basic default value but you may easily change it to any table you like.
             */

            'role_has_permissions' => 'role_has_permissions',
        ],

        'column_names' => [
            /*
             * Change this if you want to name the related pivots other than defaults
             */
            'role_pivot_key' => null, // default 'role_id',
            'permission_pivot_key' => null, // default 'permission_id',

            /*
             * Change this if you want to name the related model primary key other than
             * `model_id`.
             *
             * For example, this would be nice if your primary keys are all UUIDs. In
             * that case, name this `model_uuid`.
             */

            'model_morph_key' => 'model_id',

            /*
             * Change this if you want to use the teams feature and your related model's
             * foreign key is other than `team_id`.
             */

            'team_foreign_key' => 'tenant_id',
        ],

        /*
         * When set to true, the method for checking permissions will be registered on the gate.
         * Set this to false if you want to implement custom logic for checking permissions.
         */

        'register_permission_check_method' => true,

        /*
         * When set to true, Laravel\Octane\Events\OperationTerminated event listener will be registered
         * this will refresh permissions on every TickTerminated, TaskTerminated and RequestTerminated
         * NOTE: This should not be needed in most cases, but an Octane/Vapor combination benefited from it.
         */
        'register_octane_reset_listener' => false,

        /*
         * When enabled, events will fire when roles or permissions are assigned/unassigned.
         * Set to true and create event listeners to watch for these events.
         */
        'events_enabled' => false,

        /*
         * When set to true, migrations enable a SQLite-friendly workaround.
         */
        'testing' => false,

        /*
         * Passport Client Credentials Grant
         * When set to true the package will use Passports Client to check permissions
         */

        'use_passport_client_credentials' => false,

        /*
         * When set to true, the required permission names are added to exception messages.
         * This could be considered an information leak in some contexts, so the default
         * setting is false here for optimum safety.
         */

        'display_permission_in_exception' => false,

        /*
         * When set to true, the required role names are added to exception messages.
         * This could be considered an information leak in some contexts, so the default
         * setting is false here for optimum safety.
         */

        'display_role_in_exception' => false,

        /*
         * By default wildcard permission lookups are disabled.
         * See documentation to understand supported syntax.
         */

        'enable_wildcard_permission' => false,

        /* Cache-specific settings */

        'cache' => [

            /*
             * By default all permissions are cached for 24 hours to speed up performance.
             * When permissions or roles are updated the cache is flushed automatically.
             */

            'expiration_time' => \DateInterval::createFromDateString('24 hours'),

            /*
             * The cache key used to store all permissions.
             */

            'key' => 'keystone.permission.cache',

            /*
             * You may optionally indicate a specific cache driver to use for permission and
             * role caching using any of the `store` drivers listed in the cache.php config
             * file. Using 'default' here means to use the `default` set in cache.php.
             */

            'store' => 'default',
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
