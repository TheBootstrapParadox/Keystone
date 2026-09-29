<?php

use BSPDX\Keystone\Http\Controllers\RolePermissionController;
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
*/

// Role & Permission Management API
Route::middleware(['auth'])->prefix('api')->group(function () {
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

    // Assign (POST adds) or sync (PUT replaces) roles and permissions for users
    Route::post('/users/{user}/roles', [RolePermissionController::class, 'assignRoles'])
        ->middleware('permission:assign-roles')
        ->name('api.users.roles.assign');

    Route::put('/users/{user}/roles', [RolePermissionController::class, 'syncRoles'])
        ->middleware('permission:assign-roles')
        ->name('api.users.roles.sync');

    Route::post('/users/{user}/permissions', [RolePermissionController::class, 'assignPermissions'])
        ->middleware('permission:assign-permissions')
        ->name('api.users.permissions.assign');

    Route::put('/users/{user}/permissions', [RolePermissionController::class, 'syncPermissions'])
        ->middleware('permission:assign-permissions')
        ->name('api.users.permissions.sync');

    // Get user roles and permissions
    Route::get('/users/{user}/roles-permissions', [RolePermissionController::class, 'userRolesPermissions'])
        ->middleware('permission:view-users')
        ->name('api.users.roles-permissions');

    // Assign (POST adds) or sync (PUT replaces) permissions for roles
    Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'assignPermissionsToRole'])
        ->middleware('permission:assign-permissions')
        ->name('api.roles.permissions.assign');

    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'syncRolePermissions'])
        ->middleware('permission:assign-permissions')
        ->name('api.roles.permissions.sync');
});
