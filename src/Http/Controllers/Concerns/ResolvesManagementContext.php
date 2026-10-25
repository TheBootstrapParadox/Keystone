<?php

namespace BSPDX\Keystone\Http\Controllers\Concerns;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Http\Support\PrivilegeEscalationGuard;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Support\MultiTenancy;
use BSPDX\Keystone\Support\UserModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Caller and target resolution shared by the management API controllers.
 *
 * @internal
 */
trait ResolvesManagementContext
{
    /**
     * The caller's tenant in multi-tenant mode (null when tenant-less or single-tenant).
     */
    protected function callerTenantId(Request $request): ?string
    {
        return MultiTenancy::enabled()
            ? $request->user()?->getAttribute('tenant_id')
            : null;
    }

    /**
     * The requested guard_name, or null when it is missing or not a string.
     *
     * Used to build rules before validation runs; validation still rejects a
     * guard_name that is not a string.
     */
    protected function requestedGuard(Request $request): ?string
    {
        $guard = $request->input('guard_name');

        return is_string($guard) ? $guard : null;
    }

    /**
     * The tenant a model belongs to, as the reference for resolving names.
     */
    protected function tenantOf(Model $model): ?string
    {
        return MultiTenancy::enabled() ? $model->getAttribute('tenant_id') : null;
    }

    /**
     * Global roles and permissions are shared by every tenant, so a tenant
     * caller may read them but never change or delete them.
     */
    protected function denyTenantWriteToGlobal(Request $request, KeystoneRole|KeystonePermission $model): void
    {
        if ($this->callerTenantId($request) !== null && $model->tenant_id === null) {
            abort(403, 'Global roles and permissions cannot be modified by a tenant.');
        }
    }

    protected function escalationGuard(Request $request): PrivilegeEscalationGuard
    {
        return new PrivilegeEscalationGuard($request->user());
    }

    /**
     * Resolve the {user} route segment to the configured user model.
     *
     * Resolved here rather than via a global Route::bind('user') so the
     * consuming app's own {user} bindings are left alone. If the app does bind
     * {user} (Route::model / Route::bind), the bound model arrives here and is
     * used as-is. Users in another tenant are reported as missing (404)
     * rather than forbidden, however they were resolved.
     */
    protected function resolveUser(Request $request, Model|string $user): Model&KeystoneUser
    {
        $userModel = UserModel::className();

        if (! $user instanceof $userModel) {
            $user = $userModel::query()->whereKey($user instanceof Model ? $user->getKey() : $user)->firstOrFail();
        }

        $callerTenant = $this->callerTenantId($request);

        if ($callerTenant !== null && $user->getAttribute('tenant_id') !== $callerTenant) {
            abort(404);
        }

        return $user;
    }

    /**
     * Validate the optional per_page / page query parameters of a list route.
     *
     * @return array{per_page?: int|string|null, page?: int|string|null}
     */
    protected function validatePagination(Request $request): array
    {
        return $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * The meta object of a paginated list response (no links, so responses
     * don't depend on where the routes are mounted).
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{current_page: int, per_page: int, last_page: int, total: int}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }
}
