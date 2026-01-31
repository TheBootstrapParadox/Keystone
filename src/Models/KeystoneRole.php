<?php

namespace BSPDX\Keystone\Models;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * @method $this givePermissionTo(...$permissions)
 * @method $this syncPermissions(...$permissions)
 * @method $this revokePermissionTo($permission)
 * @method bool hasPermissionTo($permission, ?string $guardName = null)
 * @method Collection getPermissionNames()
 */
class KeystoneRole extends Role
{
    /**
     * Determine if this role is the super admin role.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->name === config('keystone.rbac.super_admin_role', 'super-admin');
    }
}
