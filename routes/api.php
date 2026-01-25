<?php

use Illuminate\Support\Facades\Route;
use BSPDX\Keystone\Http\Controllers\RolePermissionController;
use BSPDX\Keystone\Http\Controllers\TwoFactorAuthController;
use BSPDX\Keystone\Http\Controllers\PasskeyAuthController;

/*
|--------------------------------------------------------------------------
| Keystone API Routes
|--------------------------------------------------------------------------
|
| These are example API routes for the BSPDX Keystone package.
| Copy these routes to your routes/api.php file and customize as needed.
|
| These routes are protected with Sanctum authentication.
|
*/

// Role & Permission Management API
Route::middleware(['auth:sanctum'])->prefix('api')->group(function () {
    // View roles and permissions
    Route::get('/roles', [RolePermissionController::class, 'roles'])
        ->middleware('permission:view-roles')
        ->name('api.roles.index');

    Route::get('/permissions', [RolePermissionController::class, 'permissions'])
        ->middleware('permission:view-permissions')
        ->name('api.permissions.index');

    // Create roles and permissions
    Route::post('/roles', [RolePermissionController::class, 'createRole'])
        ->middleware('permission:create-roles')
        ->name('api.roles.store');

    Route::post('/permissions', [RolePermissionController::class, 'createPermission'])
        ->middleware('permission:create-permissions')
        ->name('api.permissions.store');

    // Delete roles and permissions
    Route::delete('/roles/{role}', [RolePermissionController::class, 'deleteRole'])
        ->middleware('permission:delete-roles')
        ->name('api.roles.destroy');

    Route::delete('/permissions/{permission}', [RolePermissionController::class, 'deletePermission'])
        ->middleware('permission:delete-permissions')
        ->name('api.permissions.destroy');

    // Assign roles and permissions to users
    Route::post('/users/{user}/roles', [RolePermissionController::class, 'assignRoles'])
        ->middleware('permission:assign-roles')
        ->name('api.users.roles.assign');

    Route::post('/users/{user}/permissions', [RolePermissionController::class, 'assignPermissions'])
        ->middleware('permission:assign-permissions')
        ->name('api.users.permissions.assign');

    // Get user roles and permissions
    Route::get('/users/{user}/roles-permissions', [RolePermissionController::class, 'userRolesPermissions'])
        ->middleware('permission:view-users')
        ->name('api.users.roles-permissions');

    // Assign permissions to roles
    Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'assignPermissionsToRole'])
        ->middleware('permission:assign-permissions')
        ->name('api.roles.permissions.assign');
});

// Two-Factor Authentication API
Route::middleware(['auth:sanctum'])->prefix('api/user')->group(function () {
    Route::post('/two-factor-authentication', [TwoFactorAuthController::class, 'store'])
        ->name('api.two-factor.store');

    Route::post('/confirmed-two-factor-authentication', [TwoFactorAuthController::class, 'confirm'])
        ->name('api.two-factor.confirm');

    Route::delete('/two-factor-authentication', [TwoFactorAuthController::class, 'destroy'])
        ->name('api.two-factor.destroy');

    Route::get('/two-factor-recovery-codes', [TwoFactorAuthController::class, 'recoveryCodes'])
        ->name('api.two-factor.recovery-codes');

    Route::post('/two-factor-recovery-codes', [TwoFactorAuthController::class, 'regenerateRecoveryCodes'])
        ->name('api.two-factor.recovery-codes.regenerate');
});

// Passkey API
Route::middleware(['auth:sanctum'])->prefix('api/user')->group(function () {
    Route::get('/passkeys', [PasskeyAuthController::class, 'index'])
        ->name('api.passkeys.index');

    Route::post('/passkeys/options', [PasskeyAuthController::class, 'registerOptions'])
        ->name('api.passkeys.register.options');

    Route::post('/passkeys', [PasskeyAuthController::class, 'store'])
        ->name('api.passkeys.store');

    Route::delete('/passkeys/{passkey}', [PasskeyAuthController::class, 'destroy'])
        ->name('api.passkeys.destroy');
});
