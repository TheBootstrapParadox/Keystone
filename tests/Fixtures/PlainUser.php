<?php

namespace Tests\Fixtures;

use BSPDX\Keystone\Traits\HasKeystone;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A user model that uses HasKeystone but does not implement KeystoneUser,
 * standing in for another guard's model or a forgotten `implements`.
 */
class PlainUser extends Authenticatable
{
    use HasKeystone;

    protected $table = 'users';

    protected $guarded = [];
}
