<?php

use BSPDX\Keystone\Http\Controllers\LoginController;
use BSPDX\Keystone\Http\Controllers\PasskeyAuthController;
use BSPDX\Keystone\Http\Controllers\ProfileController;
use BSPDX\Keystone\Http\Controllers\TwoFactorAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Keystone Web Routes
|--------------------------------------------------------------------------
|
| These are example routes for the BSPDX Keystone package.
| Copy these routes to your routes/web.php file and customize as needed.
|
| Make sure to add the 'web' middleware group and authentication as needed.
|
*/

// Splash Page
Route::get('/', function () {
    return view('splash');
})->name('home');

// Profile Routes
Route::middleware(config('keystone.profile.middleware', ['web', 'auth']))->group(function () {
    Route::get(config('keystone.profile.path', '/profile'), [ProfileController::class, 'show'])
        ->name('keystone.profile.show');

    Route::put(config('keystone.profile.path', '/profile').'/auth-preferences', [ProfileController::class, 'updateAuthPreferences'])
        ->middleware('password-confirm')
        ->name('keystone.profile.auth-preferences.update');
});

// Passwordless Login Routes (guest)
Route::middleware(['web', 'guest', 'keystone.feature:passwordless_login'])->group(function () {
    // Get available auth methods for an email
    Route::post('/login/methods', [LoginController::class, 'getAuthMethods'])
        ->name('keystone.login.methods');

    // TOTP-only login (when password not required)
    Route::post('/login/totp', [LoginController::class, 'authenticateWithTotp'])
        ->name('keystone.login.totp');
});

// Two-Factor Authentication Routes
Route::middleware(['web', 'auth', 'keystone.feature:two_factor'])->group(function () {
    // Enable 2FA setup view (read-only, no password confirm needed)
    Route::get('/user/two-factor-authentication', [TwoFactorAuthController::class, 'create'])
        ->name('two-factor.enable');

    // Recovery codes (read-only)
    Route::get('/user/two-factor-recovery-codes', [TwoFactorAuthController::class, 'recoveryCodes'])
        ->name('two-factor.recovery-codes');

    // Mutating 2FA actions require password confirmation
    Route::middleware('password-confirm')->group(function () {
        Route::post('/user/two-factor-authentication', [TwoFactorAuthController::class, 'store'])
            ->name('two-factor.store');

        Route::post('/user/confirmed-two-factor-authentication', [TwoFactorAuthController::class, 'confirm'])
            ->name('two-factor.confirm');

        Route::delete('/user/two-factor-authentication', [TwoFactorAuthController::class, 'destroy'])
            ->name('two-factor.destroy');

        Route::post('/user/two-factor-recovery-codes', [TwoFactorAuthController::class, 'regenerateRecoveryCodes'])
            ->name('two-factor.recovery-codes.regenerate');
    });
});

// Passkey Routes
Route::middleware(['web', 'keystone.feature:passkeys'])->group(function () {
    // Passkey login (guest)
    Route::get('/passkey/login', [PasskeyAuthController::class, 'loginView'])
        ->name('passkeys.login')
        ->middleware('guest');

    Route::post('/passkey/login/options', [PasskeyAuthController::class, 'loginOptions'])
        ->name('passkeys.login.options')
        ->middleware('guest');

    Route::post('/passkey/authenticate', [PasskeyAuthController::class, 'authenticate'])
        ->name('passkeys.authenticate')
        ->middleware('guest');

    // Passkey management (authenticated)
    Route::middleware(['auth'])->group(function () {
        // Read-only passkey views (no password confirm needed)
        Route::get('/user/passkeys', [PasskeyAuthController::class, 'registerView'])
            ->name('passkeys.register.view');

        Route::post('/user/passkeys/options', [PasskeyAuthController::class, 'registerOptions'])
            ->name('passkeys.register.options');

        // Mutating passkey actions require password confirmation
        Route::middleware('password-confirm')->group(function () {
            Route::post('/user/passkeys', [PasskeyAuthController::class, 'store'])
                ->name('passkeys.register');

            Route::delete('/user/passkeys/{passkeyId}', [PasskeyAuthController::class, 'destroy'])
                ->name('passkeys.destroy');
        });
    });
});

// Example protected routes using Keystone middleware
Route::middleware(['web', 'auth', '2fa'])->group(function () {
    // Routes that require 2FA to be enabled (if required by role)
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

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
