<?php

namespace Tests\Fixtures;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Traits\HasKeystone;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A user model whose primary key column is user_id, for testing pivot queries.
 */
class CustomKeyUser extends Authenticatable implements KeystoneUser
{
    use HasKeystone;

    protected $table = 'custom_key_users';

    protected $primaryKey = 'user_id';

    protected $guarded = [];
}
