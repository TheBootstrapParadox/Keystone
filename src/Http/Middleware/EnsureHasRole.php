<?php

namespace BSPDX\Keystone\Http\Middleware;

use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasRole
{
    /**
     * The authorization service instance.
     */
    protected AuthorizationServiceInterface $authorizationService;

    /**
     * Create a new middleware instance.
     */
    public function __construct(AuthorizationServiceInterface $authorizationService)
    {
        $this->authorizationService = $authorizationService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // Super admins bypass all role checks
        if ($this->authorizationService->userCanBypassPermissions($request->user())) {
            return $next($request);
        }

        if (! $this->authorizationService->userHasAnyRole($request->user(), $roles)) {
            abort(403, 'You do not have the required role to access this resource.');
        }

        return $next($request);
    }
}
