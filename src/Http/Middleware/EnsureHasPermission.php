<?php

namespace BSPDX\Keystone\Http\Middleware;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Services\Contracts\AuthorizationServiceInterface;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPermission
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(protected AuthorizationServiceInterface $authorizationService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $user = $this->keystoneUser($user);

        // Super admins bypass all permission checks
        if ($this->authorizationService->userCanBypassPermissions($user)) {
            return $next($request);
        }

        if (! $this->authorizationService->userHasAnyPermission($user, $permissions)) {
            abort(403, 'You do not have the required permission to access this resource.');
        }

        return $next($request);
    }

    /**
     * The authenticated user as a Keystone user. Any other user model (for
     * example another guard's) holds no Keystone permission, so it is refused.
     */
    private function keystoneUser(Authenticatable $user): KeystoneUser
    {
        if (! $user instanceof KeystoneUser) {
            abort(403, 'You do not have the required permission to access this resource.');
        }

        return $user;
    }
}
