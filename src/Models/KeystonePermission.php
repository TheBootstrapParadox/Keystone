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
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'guard_name', 'title', 'description'];

    /**
     * Get display name (title if available, otherwise name).
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->title ?? $this->name;
    }
}
