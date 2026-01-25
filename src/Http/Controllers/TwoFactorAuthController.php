<?php

namespace BSPDX\Keystone\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Contracts\View\View;

class TwoFactorAuthController
{
    /**
     * Display the two-factor authentication setup view.
     */
    public function create(Request $request): View
    {
        return view('keystone::two-factor.enable', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        // Generate 2FA secret
        $user->forceFill([
            'two_factor_secret' => encrypt(app('pragmarx.google2fa')->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode($this->generateRecoveryCodes())),
        ])->save();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Two-factor authentication has been enabled.',
                'qr_code' => $user->twoFactorQrCodeSvg(),
                'recovery_codes' => $this->getRecoveryCodes($user),
            ]);
        }

        return redirect()->back()->with([
            'status' => 'two-factor-enabled',
            'recovery_codes' => $this->getRecoveryCodes($user),
        ]);
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirm(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!app('pragmarx.google2fa')->verifyKey(
            decrypt($user->two_factor_secret),
            $request->code
        )) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'The provided code was invalid.'], 422);
            }

            return redirect()->back()->withErrors([
                'code' => 'The provided code was invalid.',
            ]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Two-factor authentication has been confirmed.']);
        }

        return redirect()->back()->with('status', 'two-factor-confirmed');
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Two-factor authentication has been disabled.']);
        }

        return redirect()->back()->with('status', 'two-factor-disabled');
    }

    /**
     * Display the user's recovery codes.
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $codes = $this->getRecoveryCodes($request->user());

        return response()->json([
            'codes' => $codes,
            'recovery_codes' => $codes, // Backwards compatibility
        ]);
    }

    /**
     * Regenerate the user's recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($this->generateRecoveryCodes())),
        ])->save();

        $codes = $this->getRecoveryCodes($request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'codes' => $codes,
                'recovery_codes' => $codes, // Backwards compatibility
            ]);
        }

        return redirect()->back()->with([
            'status' => 'recovery-codes-regenerated',
            'recovery_codes' => $codes,
        ]);
    }

    /**
     * Generate new recovery codes.
     */
    protected function generateRecoveryCodes(): array
    {
        $count = config('keystone.two_factor.recovery_codes_count', 8);

        return Collection::times($count, function () {
            return Str::random(10) . '-' . Str::random(10);
        })->toArray();
    }

    /**
     * Get the user's recovery codes.
     */
    protected function getRecoveryCodes($user): array
    {
        return json_decode(decrypt($user->two_factor_recovery_codes), true);
    }
}
