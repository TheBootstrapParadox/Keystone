<?php

namespace BSPDX\Keystone\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LoginController
{
    /**
     * Get available authentication methods for an email.
     *
     * This endpoint allows the login form to determine which authentication
     * methods are available for a user before they enter credentials.
     */
    public function getAuthMethods(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // Get the user model class from config or default to App\Models\User
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        $user = $userModel::where('email', $request->email)->first();

        if (!$user) {
            // Don't reveal if user exists - return default methods
            return response()->json(['methods' => ['password']]);
        }

        // Check if user has the getAvailableAuthMethods method (from HasKeystone trait)
        if (method_exists($user, 'getAvailableAuthMethods')) {
            return response()->json([
                'methods' => $user->getAvailableAuthMethods(),
            ]);
        }

        // Fallback: assume password is always available
        return response()->json(['methods' => ['password']]);
    }

    /**
     * Authenticate using TOTP code only (passwordless).
     *
     * This allows users who have enabled TOTP-only login to authenticate
     * using just their email and authenticator code.
     */
    public function authenticateWithTotp(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'totp_code' => 'required|string|size:6',
        ]);

        // Get the user model class from config
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        $user = $userModel::where('email', $request->email)->first();

        // Check if user exists and has TOTP login enabled
        if (!$user || !$user->allow_totp_login || !$user->hasTwoFactorEnabled()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid credentials.'], 401);
            }
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        // Verify the TOTP code
        $valid = app('pragmarx.google2fa')->verifyKey(
            decrypt($user->two_factor_secret),
            $request->totp_code
        );

        if (!$valid) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid authentication code.'], 401);
            }
            return back()->withErrors(['totp_code' => 'Invalid authentication code.']);
        }

        // Log the user in
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Authentication successful.',
                'redirect' => config('keystone.redirects.login', '/dashboard'),
            ]);
        }

        return redirect()->intended(config('keystone.redirects.login', '/dashboard'));
    }
}
