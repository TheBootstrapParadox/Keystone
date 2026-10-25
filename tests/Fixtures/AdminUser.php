<?php

namespace Tests\Fixtures;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Traits\HasKeystone;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A second, integer-keyed model that opts in to Keystone (for example an
 * admin model on another guard).
 */
class AdminUser extends Authenticatable implements KeystoneUser
{
    use HasKeystone;

    protected $table = 'admins';

    protected $guarded = [];
}
