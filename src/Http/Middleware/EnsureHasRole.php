<?php

namespace BSPDX\Keystone\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;

class EnsureHasRole
{
    /**
     * The authorization service instance.
     *
     * @var AuthorizationServiceInterface
     */
    protected AuthorizationServiceInterface $authorizationService;

    /**
     * Create a new middleware instance.
     *
     * @param AuthorizationServiceInterface $authorizationService
     */
    public function __construct(AuthorizationServiceInterface $authorizationService)
    {
        $this->authorizationService = $authorizationService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Super admins bypass all role checks
        if ($this->authorizationService->userCanBypassPermissions($request->user())) {
            return $next($request);
        }

        if (!$this->authorizationService->userHasAnyRole($request->user(), $roles)) {
            abort(403, 'You do not have the required role to access this resource.');
        }

        return $next($request);
    }
}
