<?php

namespace BSPDX\Keystone\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordConfirm
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('keystone.profile.require_password_confirm', true)) {
            return $next($request);
        }

        $timeout = config('keystone.session.password_timeout', 10800);
        $lastConfirmed = $request->session()->get('auth.password_confirmed_at', 0);

        if (time() - $lastConfirmed > $timeout) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Password confirmation required.'], 423);
            }

            return redirect()->guest(route('password.confirm'));
        }

        return $next($request);
    }
}
