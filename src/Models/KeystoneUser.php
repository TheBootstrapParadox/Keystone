<?php

namespace BSPDX\Keystone\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Spatie\LaravelPasskeys\Contracts\HasPasskeys;
use BSPDX\Keystone\Traits\HasKeystone;

class KeystoneUser extends Authenticatable implements HasPasskeys, MustVerifyEmail
{
    use HasKeystone;

    /**
     * Constructor to configure table name and primary key type based on config.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set table from config
        $this->table = config('keystone.user.table_name', 'users');

        // Use UUID trait if configured
        if (config('keystone.user.primary_key_type') === 'uuid') {
            $this->usesUuids = true;
            $this->keyType = 'string';
            $this->incrementing = false;
        }
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'allow_passkey_login',
        'allow_totp_login',
        'require_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'allow_passkey_login' => 'boolean',
            'allow_totp_login' => 'boolean',
            'require_password' => 'boolean',
        ];
    }

    /**
     * Conditionally use HasUuids trait for UUID primary keys.
     */
    public function uniqueIds(): array
    {
        if (config('keystone.user.primary_key_type') === 'uuid') {
            return ['id'];
        }

        return [];
    }
}
