<?php

namespace BSPDX\Keystone\Models;

use Spatie\Permission\Models\Role;

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
