<?php

namespace Tests\Fixtures;

use BSPDX\Keystone\Traits\HasKeystone;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A ULID-keyed user model, for testing the pivot model_id column type.
 */
class UlidUser extends Authenticatable
{
    use HasKeystone, HasUlids;

    protected $table = 'ulid_users';
}
