<?php

namespace Tests\Fixtures;

use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A user model with soft deletes, for testing assignment cleanup.
 */
class SoftDeletingUser extends User
{
    use SoftDeletes;

    protected $table = 'users';
}
