<?php

namespace BSPDX\Keystone\Models;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * @method $this assignRole(...$roles)
 * @method $this removeRole(...$role)
 * @method $this syncRoles(...$roles)
 * @method bool hasRole($roles, ?string $guard = null)
 * @method bool hasAnyRole(...$roles)
 * @method Collection getRoleNames()
 */
class KeystonePermission extends Permission
{
    // Keystone-specific methods can be added here in the future
}
