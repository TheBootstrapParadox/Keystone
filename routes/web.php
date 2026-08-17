<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Keystone Web Routes
|--------------------------------------------------------------------------
|
| These are example RBAC-protected routes for the BSPDX Keystone package.
| Copy these routes to your routes/web.php file and customize as needed.
|
| Keystone does not provide authentication. Apply your own auth middleware
| (Fortify, Breeze, or anything else) alongside the 'role'/'permission'
| middleware shown below.
|
*/

// Example RBAC protected routes
Route::middleware(['web', 'auth', 'role:admin'])->group(function () {
    Route::get('/admin', function () {
        return 'Admin Dashboard';
    });
});

Route::middleware(['web', 'auth', 'permission:edit-posts'])->group(function () {
    Route::get('/posts/edit', function () {
        return 'Edit Posts';
    });
});
