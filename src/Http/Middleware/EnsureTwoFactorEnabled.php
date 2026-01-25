<?php

namespace BSPDX\Keystone\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Check if 2FA is required for the user's role
        if (method_exists($request->user(), 'requires2FA') && $request->user()->requires2FA()) {
            // Check if 2FA is enabled
            if (!method_exists($request->user(), 'hasTwoFactorEnabled') || !$request->user()->hasTwoFactorEnabled()) {
                return redirect()->route('two-factor.enable')
                    ->with('warning', 'Two-factor authentication is required for your account. Please enable it to continue.');
            }
        }

        return $next($request);
    }
}
