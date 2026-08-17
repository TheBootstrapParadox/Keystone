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
    | Specify which User model to use. Keystone does not own a User model —
    | this should point at your own application's authenticatable model,
    | which should use the BSPDX\Keystone\Traits\HasKeystone trait.
    |
    */

    'user' => [
        // User model class to use.
        // Default: null (uses config('auth.providers.users.model'))
        'model' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggle Keystone-specific functionality on/off.
    |
    */

    'features' => [
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

        // Super admin role that bypasses all permission checks
        'super_admin_role' => 'super-admin',
    ],

];
