# User Model Configuration

Keystone provides flexible User model configuration, allowing you to use either your own User model or the built-in KeystoneUser model.

## Default: Host App User (Recommended for existing projects)

By default, Keystone uses your application's User model via `config('auth.providers.users.model')`.

### Requirements

Your User model should:
- Extend `Illuminate\Foundation\Auth\User`
- Use the `HasKeystone` trait
- Implement `Spatie\LaravelPasskeys\Contracts\HasPasskeys` contract
- Implement `Illuminate\Contracts\Auth\MustVerifyEmail` contract

### Example

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Spatie\LaravelPasskeys\Contracts\HasPasskeys;
use BSPDX\Keystone\Traits\HasKeystone;

class User extends Authenticatable implements HasPasskeys, MustVerifyEmail
{
    use HasKeystone;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

---

## Using Built-in KeystoneUser (Recommended for new projects)

Keystone provides a complete User model implementation that includes all necessary authentication features out of the box.

### Setup

1. **Publish Keystone config:**
   ```bash
   php artisan vendor:publish --tag=keystone-config
   ```

2. **Update `config/keystone.php`:**
   ```php
   'user' => [
       'model' => \BSPDX\Keystone\Models\KeystoneUser::class,
       'primary_key_type' => 'bigint', // or 'uuid'
       'table_name' => 'users',
   ],
   ```

3. **Update `config/auth.php`:**
   ```php
   'providers' => [
       'users' => [
           'driver' => 'eloquent',
           'model' => \BSPDX\Keystone\Models\KeystoneUser::class,
       ],
   ],
   ```

4. **Run migrations:**
   ```bash
   php artisan migrate
   php artisan db:seed --class=KeystoneSeeder
   ```

### Features

The `KeystoneUser` model includes:
- ✅ Two-Factor Authentication (TOTP)
- ✅ Passkey/WebAuthn support
- ✅ Role-Based Access Control (RBAC)
- ✅ Email verification
- ✅ API token authentication (Sanctum)
- ✅ Passwordless login options
- ✅ Configurable UUID or BigInt primary keys
- ✅ Customizable table name

---

## UUID Support

To use UUIDs for the primary key with KeystoneUser:

```php
// config/keystone.php
'user' => [
    'model' => \BSPDX\Keystone\Models\KeystoneUser::class,
    'primary_key_type' => 'uuid',
    'table_name' => 'users',
],
```

The KeystoneUser migration will automatically create the `users` table with a UUID primary key.

---

## Custom Table Names

If your application uses a custom user table name (e.g., `id_users`), Keystone automatically detects it:

### For Host App User:

```php
// app/Models/User.php
class User extends Authenticatable
{
    protected $table = 'id_users';
    // ...
}
```

Keystone migrations will automatically use `id_users` instead of `users`. No additional configuration needed!

### For KeystoneUser:

```php
// config/keystone.php
'user' => [
    'model' => \BSPDX\Keystone\Models\KeystoneUser::class,
    'primary_key_type' => 'bigint',
    'table_name' => 'id_users', // Custom table name
],
```

---

## Migration Compatibility

Keystone migrations are designed to work with any User model setup:

### Column Guards

All migrations guard against duplicate columns using `Schema::hasColumn()` checks. If your User table already has columns like `two_factor_secret` or `allow_passkey_login`, Keystone migrations will skip them.

### Dynamic Table Resolution

Migrations resolve the user table name dynamically:

```php
$authenticatable = config('auth.providers.users.model', \App\Models\User::class);
$tableName = (new $authenticatable)->getTable();
```

This ensures migrations work correctly with:
- Custom table names
- Custom namespaces (e.g., `App\Models\Id\User`)
- UUID or BigInt primary keys
- Existing column definitions

---

## Switching from Host User to KeystoneUser

If you want to migrate from using your own User model to KeystoneUser:

1. **Backup your database**

2. **Publish and update config:**
   ```bash
   php artisan vendor:publish --tag=keystone-config
   ```

   Update `config/keystone.php`:
   ```php
   'user' => [
       'model' => \BSPDX\Keystone\Models\KeystoneUser::class,
       'primary_key_type' => 'bigint',
       'table_name' => 'users',
   ],
   ```

3. **Update `config/auth.php`:**
   ```php
   'providers' => [
       'users' => [
           'driver' => 'eloquent',
           'model' => \BSPDX\Keystone\Models\KeystoneUser::class,
       ],
   ],
   ```

4. **Remove your User model** (optional):
   ```bash
   rm app/Models/User.php
   ```

5. **Clear caches:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

Your existing `users` table and data will remain intact. KeystoneUser will use the same table.

---

## Troubleshooting

### "Class 'App\Models\User' not found"

This error occurs when Keystone tries to use a User model that doesn't exist. Solutions:

1. **Create a User model** in `app/Models/User.php`
2. **OR use KeystoneUser** (see setup above)
3. **OR check `config/auth.php`** to ensure the User model path is correct

### "Table 'users' doesn't exist"

If using KeystoneUser, ensure:
1. Config is set correctly: `'model' => \BSPDX\Keystone\Models\KeystoneUser::class`
2. Migrations have been run: `php artisan migrate`

If using host app User, ensure your app's user table migration runs before Keystone migrations.

### "Column already exists"

Keystone migrations include column guards to prevent this. If you still get this error:
1. Check if you've manually added Keystone columns to your migration
2. Run `php artisan migrate:fresh` (WARNING: destroys data) or manually remove duplicate column definitions

---

## Best Practices

### New Projects
✅ Use `KeystoneUser` for a complete, ready-to-use authentication system

### Existing Projects
✅ Keep your existing User model and use `HasKeystone` trait

### Custom Requirements
If you need custom fields or business logic on the User model:
- **Option 1:** Extend `KeystoneUser` in your own model
  ```php
  namespace App\Models;

  use BSPDX\Keystone\Models\KeystoneUser as BaseUser;

  class User extends BaseUser
  {
      protected $fillable = [
          ...parent::$fillable,
          'phone',
          'company_id',
      ];
  }
  ```

- **Option 2:** Use your own User model with `HasKeystone` trait (recommended)

---

## Additional Resources

- [Keystone Configuration](../config/keystone.php)
- [HasKeystone Trait](../src/Traits/HasKeystone.php)
- [KeystoneUser Model](../src/Models/KeystoneUser.php)
- [Laravel Authentication Docs](https://laravel.com/docs/authentication)
