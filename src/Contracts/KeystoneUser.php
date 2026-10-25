<?php

namespace BSPDX\Keystone\Contracts;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * A model that holds Keystone roles and permissions.
 *
 * The HasKeystone trait implements every method; declare both on the model:
 *
 *     class User extends Authenticatable implements KeystoneUser
 *     {
 *         use HasKeystone;
 *     }
 */
interface KeystoneUser
{
    /**
     * The user's roles.
     *
     * @return MorphToMany<KeystoneRole, *>
     */
    public function roles(): MorphToMany;

    /**
     * The user's direct permissions (not via roles).
     *
     * @return MorphToMany<KeystonePermission, *>
     */
    public function permissions(): MorphToMany;

    /**
     * Add roles to the user.
     */
    public function assignRole(string|int|KeystoneRole|iterable ...$roles): static;

    /**
     * Remove roles from the user.
     */
    public function removeRole(string|int|KeystoneRole|iterable ...$roles): static;

    /**
     * Replace the user's roles with exactly the given set.
     */
    public function syncRoles(string|int|KeystoneRole|iterable ...$roles): static;

    /**
     * Give direct permissions to the user.
     */
    public function givePermissionTo(string|int|KeystonePermission|iterable ...$permissions): static;

    /**
     * Revoke direct permissions from the user.
     */
    public function revokePermissionTo(string|int|KeystonePermission|iterable ...$permissions): static;

    /**
     * Replace the user's direct permissions with exactly the given set.
     */
    public function syncPermissions(string|int|KeystonePermission|iterable ...$permissions): static;

    /**
     * Whether the user holds the role (any of them, for an iterable).
     */
    public function hasRole(string|int|KeystoneRole|iterable $roles, ?string $guard = null): bool;

    /**
     * Whether the user holds any of the given roles.
     */
    public function hasAnyRole(string|int|KeystoneRole|iterable ...$roles): bool;

    /**
     * Whether the user holds every given role.
     */
    public function hasAllRoles(string|int|KeystoneRole|iterable ...$roles): bool;

    /**
     * Whether the user holds the permission, directly or through a role.
     */
    public function hasPermissionTo(string|int|KeystonePermission $permission, ?string $guard = null): bool;

    /**
     * Every permission the user holds, directly or through a role.
     *
     * @return Collection<int, KeystonePermission>
     */
    public function getAllPermissions(): Collection;

    /**
     * Whether the user holds any of the given permissions.
     */
    public function hasAnyPermission(string|int|KeystonePermission|iterable ...$permissions): bool;

    /**
     * Whether the user holds every given permission.
     */
    public function hasAllPermissions(string|int|KeystonePermission|iterable ...$permissions): bool;

    /**
     * Whether the user holds the permission directly (not via a role).
     */
    public function hasDirectPermission(string|int|KeystonePermission $permission, ?string $guard = null): bool;

    /**
     * Whether the user holds the super-admin role.
     */
    public function isSuperAdmin(): bool;

    /**
     * Whether the user bypasses permission checks (a super-admin).
     */
    public function canBypassPermissions(): bool;
}
