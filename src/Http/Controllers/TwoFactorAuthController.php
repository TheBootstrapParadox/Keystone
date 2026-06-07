<?php

namespace BSPDX\Keystone\Http\Controllers;

use BSPDX\Keystone\Http\Controllers\Concerns\ThrottlesAuthentication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TwoFactorAuthController
{
    use ThrottlesAuthentication;

    /**
     * Display the two-factor authentication setup view.
     */
    public function create(Request $request): View
    {
        abort_if(! config('keystone.features.two_factor', false), 404);

        return view('keystone::two-factor.enable', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_if(! config('keystone.features.two_factor', false), 404);

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
        abort_if(! config('keystone.features.two_factor', false), 404);

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        $throttleKey = $this->throttleKey($request, '2fa-confirm');
        $maxAttempts = (int) config('keystone.rate_limiting.max_2fa_attempts', 3);

        if ($this->hasTooManyAttempts($throttleKey, $maxAttempts)) {
            return $this->tooManyAttemptsResponse($request, 'code', '2fa-confirm');
        }

        $google2fa = app('pragmarx.google2fa');
        $google2fa->setWindow(config('keystone.two_factor.window', 1));

        if (! $google2fa->verifyKey(
            decrypt($user->two_factor_secret),
            $request->code
        )) {
            $this->recordFailedAttempt($throttleKey);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'The provided code was invalid.'], 422);
            }

            return redirect()->back()->withErrors([
                'code' => 'The provided code was invalid.',
            ]);
        }

        $this->clearAttempts($throttleKey);

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
        abort_if(! config('keystone.features.two_factor', false), 404);

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
        abort_if(! config('keystone.features.two_factor', false), 404);

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
        abort_if(! config('keystone.features.two_factor', false), 404);

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
            return Str::random(10).'-'.Str::random(10);
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
