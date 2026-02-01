<?php

namespace BSPDX\Keystone\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;

class EnsureHasPermission
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
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Super admins bypass all permission checks
        if ($this->authorizationService->userCanBypassPermissions($request->user())) {
            return $next($request);
        }

        if (!$this->authorizationService->userHasAnyPermission($request->user(), $permissions)) {
            abort(403, 'You do not have the required permission to access this resource.');
        }

        return $next($request);
    }
}
