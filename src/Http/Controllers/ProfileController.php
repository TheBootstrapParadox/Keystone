<?php

namespace BSPDX\Keystone\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;

class ProfileController
{
    /**
     * Display the user's profile page.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        $data = [
            'user' => $user,
            'hasTwoFactor' => $user->hasTwoFactorEnabled(),
            'hasPasskeys' => $user->hasPasskeysRegistered(),
            'passkeys' => $user->passkeys ?? collect(),
        ];

        // Include roles/permissions if using Spatie and feature enabled
        if (config('keystone.features.show_permissions') && method_exists($user, 'getRoleNames')) {
            $data['roles'] = $user->getRoleNames();
            $data['permissions'] = $user->getAllPermissions()->pluck('name');
        }

        return view('keystone::profile.show', $data);
    }

    /**
     * Update the user's authentication preferences.
     */
    public function updateAuthPreferences(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'allow_passkey_login' => 'boolean',
            'allow_totp_login' => 'boolean',
            'require_password' => 'boolean',
        ]);

        // Convert checkbox values (present = true, absent = false)
        $preferences = [
            'allow_passkey_login' => $request->boolean('allow_passkey_login'),
            'allow_totp_login' => $request->boolean('allow_totp_login'),
            'require_password' => $request->boolean('require_password'),
        ];

        // Validate at least one method is enabled
        $hasPasskey = $user->hasPasskeysRegistered();
        $hasTwoFactor = $user->hasTwoFactorEnabled();

        $willHaveMethod = $preferences['require_password'] ||
            ($preferences['allow_passkey_login'] && $hasPasskey) ||
            ($preferences['allow_totp_login'] && $hasTwoFactor);

        if (!$willHaveMethod) {
            return back()->withErrors([
                'auth_preferences' => 'You must have at least one authentication method enabled.',
            ]);
        }

        // Validate passkey login requires passkeys
        if ($preferences['allow_passkey_login'] && !$hasPasskey) {
            return back()->withErrors([
                'allow_passkey_login' => 'You must register a passkey before enabling passkey login.',
            ]);
        }

        // Validate TOTP login requires 2FA
        if ($preferences['allow_totp_login'] && !$hasTwoFactor) {
            return back()->withErrors([
                'allow_totp_login' => 'You must enable two-factor authentication before enabling TOTP login.',
            ]);
        }

        $user->update($preferences);

        // Regenerate session for security
        $request->session()->regenerate();

        return back()->with('status', 'auth-preferences-updated');
    }
}
