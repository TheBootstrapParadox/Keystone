<?php

namespace BSPDX\Keystone\Http\Controllers;

use BSPDX\Keystone\Http\Controllers\Concerns\ThrottlesAuthentication;
use BSPDX\Keystone\Services\Contracts\PasskeyServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class PasskeyAuthController
{
    use ThrottlesAuthentication;

    protected const SESSION_REGISTER_OPTIONS = 'passkey-register-options';

    protected const SESSION_AUTH_OPTIONS = 'passkey-authentication-options';

    public function __construct(
        private PasskeyServiceInterface $passkeyService
    ) {}

    /**
     * Display the passkey registration view.
     */
    public function registerView(Request $request): View
    {
        abort_if(! config('keystone.features.passkeys', false), 404);

        return view('keystone::passkeys.register', [
            'user' => $request->user(),
            'passkeys' => $request->user()->passkeys,
        ]);
    }

    /**
     * Generate passkey registration options.
     */
    public function registerOptions(Request $request): JsonResponse|RedirectResponse
    {
        abort_if(! config('keystone.features.passkeys', false), 404);

        if (! config('keystone.passkey.allow_multiple', true) && $request->user()->passkeys()->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Only one passkey is allowed per account.'], 422);
            }

            return redirect()->back()->withErrors(['passkey' => 'Only one passkey is allowed per account.']);
        }

        $optionsJson = $this->passkeyService->generateRegisterOptions($request->user());

        // Store options in session for validation during registration
        Session::flash(self::SESSION_REGISTER_OPTIONS, $optionsJson);

        return response()->json(json_decode($optionsJson, true));
    }

    /**
     * Store a new passkey for the authenticated user.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_if(! config('keystone.features.passkeys', false), 404);

        if (! config('keystone.passkey.allow_multiple', true) && $request->user()->passkeys()->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Only one passkey is allowed per account.'], 422);
            }

            return redirect()->back()->withErrors(['passkey' => 'Only one passkey is allowed per account.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'credential' => ['required'],
            'options' => ['required'],
        ]);

        // Get credential and options as JSON strings
        $credentialJson = is_string($validated['credential'])
            ? $validated['credential']
            : json_encode($validated['credential']);

        $optionsJson = is_string($validated['options'])
            ? $validated['options']
            : json_encode($validated['options']);

        try {
            $this->passkeyService->storePasskey(
                user: $request->user(),
                passkeyJson: $credentialJson,
                optionsJson: $optionsJson,
                additionalProperties: ['name' => $validated['name']],
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Passkey registered successfully.',
                ]);
            }

            return redirect()->back()->with('status', 'passkey-registered');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to register passkey: '.$e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors([
                'passkey' => 'Failed to register passkey. Please try again.',
            ]);
        }
    }

    /**
     * Delete a passkey.
     */
    public function destroy(Request $request, string $passkeyId): RedirectResponse|JsonResponse
    {
        abort_if(! config('keystone.features.passkeys', false), 404);

        $passkey = $request->user()->passkeys()->findOrFail($passkeyId);
        $passkey->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Passkey deleted successfully.',
            ]);
        }

        return redirect()->back()->with('status', 'passkey-deleted');
    }

    /**
     * Display the passkey login view.
     */
    public function loginView(): View
    {
        abort_if(! config('keystone.features.passkeys', false), 404);

        return view('keystone::passkeys.login');
    }

    /**
     * Generate passkey authentication options.
     */
    public function loginOptions(Request $request): JsonResponse
    {
        abort_if(! config('keystone.features.passkeys', false), 404);

        // This also stores options in session via Spatie's action
        $optionsJson = $this->passkeyService->generateAuthenticationOptions();

        return response()->json(json_decode($optionsJson, true));
    }

    /**
     * Authenticate a user using a passkey.
     */
    public function authenticate(Request $request): RedirectResponse|JsonResponse
    {
        abort_if(! config('keystone.features.passkeys', false), 404);

        $validated = $request->validate([
            'credential' => ['required'],
            'options' => ['required'],
        ]);

        $throttleKey = $this->throttleKey($request, 'passkey-auth');
        $maxAttempts = (int) config('keystone.rate_limiting.max_passkey_attempts', 3);

        if ($this->hasTooManyAttempts($throttleKey, $maxAttempts)) {
            return $this->tooManyAttemptsResponse($request, 'passkey', 'passkey-auth');
        }

        // Get credential and options as JSON strings
        $credentialJson = is_string($validated['credential'])
            ? $validated['credential']
            : json_encode($validated['credential']);

        $optionsJson = is_string($validated['options'])
            ? $validated['options']
            : json_encode($validated['options']);

        try {
            $passkey = $this->passkeyService->findPasskeyToAuthenticate($credentialJson, $optionsJson);

            if (! $passkey) {
                throw new \Exception('Invalid passkey credential.');
            }

            $user = $this->passkeyService->getAuthenticatableFromPasskey($passkey);

            $this->clearAttempts($throttleKey);

            auth()->login($user, remember: true);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Authentication successful.',
                    'redirect' => config('keystone.redirects.login', '/dashboard'),
                ]);
            }

            return redirect()->intended(config('keystone.redirects.login', '/dashboard'));
        } catch (\Exception $e) {
            $this->recordFailedAttempt($throttleKey);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Authentication failed: '.$e->getMessage(),
                ], 401);
            }

            return redirect()->back()->withErrors([
                'passkey' => 'Authentication failed. Please try again.',
            ]);
        }
    }

    /**
     * Get all passkeys for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        abort_if(! config('keystone.features.passkeys', false), 404);

        $passkeys = $request->user()->passkeys()->get()->map(function ($passkey) {
            return [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'created_at' => $passkey->created_at->toDateTimeString(),
                'last_used_at' => $passkey->last_used_at?->toDateTimeString(),
            ];
        });

        return response()->json(['passkeys' => $passkeys]);
    }

    /**
     * Display the passkey second-factor challenge view.
     */
    public function twofaChallengeView(): View
    {
        abort_if(! config('keystone.features.passkey_2fa', false), 404);

        return view('keystone::passkeys.2fa-challenge');
    }

    protected const SESSION_2FA_OPTIONS = 'passkey_2fa_options';

    /**
     * Generate passkey authentication options for the second-factor challenge.
     *
     * Stores the challenge in the session so twofaVerify() can retrieve it
     * server-side, preventing client-supplied or replayed challenges.
     */
    public function twofaChallengeOptions(Request $request): JsonResponse
    {
        abort_if(! config('keystone.features.passkey_2fa', false), 404);

        $optionsJson = $this->passkeyService->generateAuthenticationOptions();

        // Store server-side so the client cannot supply or replay a challenge.
        $request->session()->put(self::SESSION_2FA_OPTIONS, $optionsJson);

        return response()->json(json_decode($optionsJson, true));
    }

    /**
     * Verify a passkey as a second factor for an already-authenticated user.
     *
     * Challenge is pulled from the session (not the request body) to prevent
     * client-supplied or replayed challenges. The stored challenge is consumed
     * on first use so it cannot be replayed.
     *
     * Ownership is verified: the resolved passkey must belong to the
     * authenticated user, preventing cross-account 2FA flag injection.
     */
    public function twofaVerify(Request $request): RedirectResponse|JsonResponse
    {
        abort_if(! config('keystone.features.passkey_2fa', false), 404);

        $validated = $request->validate([
            'credential' => ['required'],
        ]);

        $throttleKey = $this->throttleKey($request, 'passkey-2fa');
        $maxAttempts = (int) config('keystone.rate_limiting.max_passkey_attempts', 3);

        if ($this->hasTooManyAttempts($throttleKey, $maxAttempts)) {
            return $this->tooManyAttemptsResponse($request, 'passkey', 'passkey-2fa');
        }

        // Pull the challenge from the session (single-use — prevents replay).
        $optionsJson = $request->session()->pull(self::SESSION_2FA_OPTIONS);

        if (! $optionsJson) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'No active challenge. Please request a new one.'], 422);
            }

            return redirect()->back()->withErrors(['passkey' => 'No active challenge. Please request a new one.']);
        }

        $credentialJson = is_string($validated['credential'])
            ? $validated['credential']
            : json_encode($validated['credential']);

        try {
            $passkey = $this->passkeyService->findPasskeyToAuthenticate($credentialJson, $optionsJson);

            if (! $passkey) {
                throw new \Exception('Invalid passkey credential.');
            }

            // Verify the passkey belongs to the authenticated user.
            if ((string) $passkey->authenticatable_id !== (string) $request->user()->getKey()) {
                throw new \Exception('Invalid passkey credential.');
            }

            $this->clearAttempts($throttleKey);

            $request->session()->put('auth.passkey_2fa_verified_at', now());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Passkey verification successful.',
                    'redirect' => config('keystone.redirects.login', '/dashboard'),
                ]);
            }

            return redirect()->intended(config('keystone.redirects.login', '/dashboard'));
        } catch (\Exception $e) {
            $this->recordFailedAttempt($throttleKey);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Verification failed: '.$e->getMessage(),
                ], 401);
            }

            return redirect()->back()->withErrors([
                'passkey' => 'Verification failed. Please try again.',
            ]);
        }
    }
}
