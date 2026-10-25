<?php

namespace BSPDX\Keystone\Http\Support;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Stops management API callers from granting access they do not hold.
 *
 * A caller who is not a super-admin may only grant a role whose permissions
 * they all hold, only grant (or add to a role) permissions they hold, and may
 * never grant, remove, or modify the super-admin role. Items the target
 * already holds are not checked. Every violation in a request is collected
 * first so the request fails as a whole.
 *
 * @internal
 */
final class PrivilegeEscalationGuard
{
    public function __construct(private ?Authenticatable $caller) {}

    /**
     * Check a set of roles about to be added to a user.
     *
     * @param  Collection<int, KeystoneRole>  $roles
     * @param  Collection<int, KeystoneRole>  $held  roles the target holds now
     *
     * @throws AuthorizationException
     */
    public function grantRoles(Collection $roles, Collection $held): void
    {
        if ($this->callerIsUnrestricted()) {
            return;
        }

        $violations = [];

        foreach ($this->notIn($roles, $held) as $role) {
            if ($role->isSuperAdmin()) {
                $violations[] = "You cannot grant the role '{$role->name}'.";

                continue;
            }

            $missing = $role->permissions
                ->reject(fn (KeystonePermission $permission) => $this->callerHolds($permission))
                ->pluck('name');

            if ($missing->isNotEmpty()) {
                $violations[] = "You cannot grant the role '{$role->name}' because you do not hold: {$missing->implode(', ')}.";
            }
        }

        $this->throwIfAny($violations);
    }

    /**
     * Check a user's role set about to be replaced by $roles.
     *
     * @param  Collection<int, KeystoneRole>  $roles
     * @param  Collection<int, KeystoneRole>  $held  roles the target holds now
     *
     * @throws AuthorizationException
     */
    public function syncRoles(Collection $roles, Collection $held): void
    {
        if ($this->callerIsUnrestricted()) {
            return;
        }

        $removedSuperAdmin = $this->notIn($held, $roles)->first(fn (KeystoneRole $role) => $role->isSuperAdmin());

        if ($removedSuperAdmin) {
            $this->throwIfAny(["You cannot remove the role '{$removedSuperAdmin->name}'."]);
        }

        $this->grantRoles($roles, $held);
    }

    /**
     * Check a set of permissions about to be added to a user or role.
     *
     * @param  Collection<int, KeystonePermission>  $permissions
     * @param  Collection<int, KeystonePermission>  $held  permissions the target holds now
     *
     * @throws AuthorizationException
     */
    public function grantPermissions(Collection $permissions, Collection $held): void
    {
        if ($this->callerIsUnrestricted()) {
            return;
        }

        $missing = $this->notIn($permissions, $held)
            ->reject(fn (KeystonePermission $permission) => $this->callerHolds($permission))
            ->pluck('name');

        if ($missing->isNotEmpty()) {
            $this->throwIfAny(["You cannot grant permissions you do not hold: {$missing->implode(', ')}."]);
        }
    }

    /**
     * Check that the caller may change the permissions of the given role.
     *
     * @throws AuthorizationException
     */
    public function modifyRole(KeystoneRole $role): void
    {
        if (! $this->callerIsUnrestricted() && $role->isSuperAdmin()) {
            $this->throwIfAny(["You cannot modify the role '{$role->name}'."]);
        }
    }

    private function callerIsUnrestricted(): bool
    {
        return $this->caller instanceof KeystoneUser && $this->caller->isSuperAdmin();
    }

    private function callerHolds(KeystonePermission $permission): bool
    {
        return $this->caller instanceof KeystoneUser
            && $this->caller->hasPermissionTo($permission->name, $permission->guard_name);
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $items
     * @param  Collection<int, TModel>  $existing
     * @return Collection<int, TModel>
     */
    private function notIn(Collection $items, Collection $existing): Collection
    {
        $existingIds = $existing->map(fn (Model $model) => $model->getKey())->all();

        /** @var Collection<int, TModel> $missing */
        $missing = $items->reject(fn (Model $model): bool => in_array($model->getKey(), $existingIds, true))->values();

        return $missing;
    }

    /**
     * @param  list<string>  $violations
     *
     * @throws AuthorizationException
     */
    private function throwIfAny(array $violations): void
    {
        if ($violations !== []) {
            throw new AuthorizationException(implode(' ', $violations));
        }
    }
}
