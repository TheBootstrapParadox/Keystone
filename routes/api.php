<?php

use BSPDX\Keystone\Http\Controllers\PermissionController;
use BSPDX\Keystone\Http\Controllers\RoleController;
use BSPDX\Keystone\Http\Controllers\RolePermissionController;
use BSPDX\Keystone\Http\Controllers\UserAccessController;
use BSPDX\Keystone\Http\Controllers\UserPermissionController;
use BSPDX\Keystone\Http\Controllers\UserRoleController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Keystone API Routes
|--------------------------------------------------------------------------
|
| These are example API routes for the BSPDX Keystone package.
| Copy these routes to your routes/api.php file and customize as needed.
|
| Keystone does not require or assume Sanctum — it's shown below only as
| the most common choice. Protect this group with whatever auth guard
| your application actually uses.
|
| throttle:60,1 is a starting point: adjust or replace it with your own
| rate limiter.
|
| SubstituteBindings is listed explicitly so {role} and {permission} resolve
| to models even when Keystone loads this file itself (keystone.load_routes)
| outside any middleware group. It is harmless inside your 'api' group.
|
*/

// Role & Permission Management API
Route::middleware(['auth', 'throttle:60,1', SubstituteBindings::class])->prefix('api')->group(function () {
    // View roles and permissions
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:view-roles')
        ->name('api.roles.index');

    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('permission:view-permissions')
        ->name('api.permissions.index');

    // Create roles and permissions
    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:create-roles')
        ->name('api.roles.store');

    Route::post('/permissions', [PermissionController::class, 'store'])
        ->middleware('permission:create-permissions')
        ->name('api.permissions.store');

    // Delete roles and permissions
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:delete-roles')
        ->name('api.roles.destroy');

    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])
        ->middleware('permission:delete-permissions')
        ->name('api.permissions.destroy');

    // Assign (POST adds) or sync (PUT replaces) roles and permissions for users
    Route::post('/users/{user}/roles', [UserRoleController::class, 'store'])
        ->middleware('permission:assign-roles')
        ->name('api.users.roles.assign');

    Route::put('/users/{user}/roles', [UserRoleController::class, 'update'])
        ->middleware('permission:assign-roles')
        ->name('api.users.roles.sync');

    Route::post('/users/{user}/permissions', [UserPermissionController::class, 'store'])
        ->middleware('permission:assign-permissions')
        ->name('api.users.permissions.assign');

    Route::put('/users/{user}/permissions', [UserPermissionController::class, 'update'])
        ->middleware('permission:assign-permissions')
        ->name('api.users.permissions.sync');

    // Get user roles and permissions
    Route::get('/users/{user}/roles-permissions', [UserAccessController::class, 'show'])
        ->middleware('permission:view-users')
        ->name('api.users.roles-permissions');

    // Assign (POST adds) or sync (PUT replaces) permissions for roles
    Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'store'])
        ->middleware('permission:assign-permissions')
        ->name('api.roles.permissions.assign');

    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update'])
        ->middleware('permission:assign-permissions')
        ->name('api.roles.permissions.sync');
});
