<?php

namespace BSPDX\Keystone\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePasskey2FA
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('keystone.features.passkey_2fa', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->hasPasskeysRegistered()) {
            return $next($request);
        }

        if ($request->session()->has('auth.passkey_2fa_verified_at')) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Passkey verification required.'], 423);
        }

        return redirect()->route('passkeys.2fa.challenge');
    }
}
