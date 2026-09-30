<?php

namespace Tests\Fixtures;

use BSPDX\Keystone\Traits\HasKeystone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A UUID-keyed user model, for testing identifier lookup in commands.
 */
class UuidUser extends Authenticatable
{
    use HasKeystone, HasUuids;

    protected $table = 'uuid_users';

    protected $guarded = [];
}
