<?php

namespace BSPDX\Keystone\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use BSPDX\Keystone\Services\Contracts\PasskeyServiceInterface;

class PasskeyAuthController
{
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
        return view('keystone::passkeys.register', [
            'user' => $request->user(),
            'passkeys' => $request->user()->passkeys,
        ]);
    }

    /**
     * Generate passkey registration options.
     */
    public function registerOptions(Request $request): JsonResponse
    {
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
                    'message' => 'Failed to register passkey: ' . $e->getMessage(),
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
        return view('keystone::passkeys.login');
    }

    /**
     * Generate passkey authentication options.
     */
    public function loginOptions(Request $request): JsonResponse
    {
        // This also stores options in session via Spatie's action
        $optionsJson = $this->passkeyService->generateAuthenticationOptions();

        return response()->json(json_decode($optionsJson, true));
    }

    /**
     * Authenticate a user using a passkey.
     */
    public function authenticate(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
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
            $passkey = $this->passkeyService->findPasskeyToAuthenticate($credentialJson, $optionsJson);

            if (!$passkey) {
                throw new \Exception('Invalid passkey credential.');
            }

            $user = $this->passkeyService->getAuthenticatableFromPasskey($passkey);

            auth()->login($user, remember: true);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Authentication successful.',
                    'redirect' => config('keystone.redirects.login', '/dashboard'),
                ]);
            }

            return redirect()->intended(config('keystone.redirects.login', '/dashboard'));
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Authentication failed: ' . $e->getMessage(),
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
}
