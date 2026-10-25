# User Model Configuration

Keystone does not own a User model. It adds roles, permissions, and tenant scoping to
whatever User model your application already authenticates with (Fortify, Breeze, or anything
else) via one interface and one trait.

## Setup

Declare `BSPDX\Keystone\Contracts\KeystoneUser` and use `BSPDX\Keystone\Traits\HasKeystone` on your User model:

```php
<?php

namespace App\Models;

use BSPDX\Keystone\Contracts\KeystoneUser;
use BSPDX\Keystone\Traits\HasKeystone;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements KeystoneUser
{
    use Notifiable, HasKeystone;

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

That's the entire integration. A trait can't declare an interface for its class, so the
`implements KeystoneUser` line is needed alongside `use HasKeystone;`; the trait implements every
method the interface declares. Keystone's services take a `KeystoneUser`, and a logged-in user
whose model doesn't implement it gets 403 from the `role` / `permission` middleware and is left
to your policies by the Gate.

The trait adds:

- `roles()` / `permissions()` relationships (tenant-aware when `keystone.features.multi_tenant` is enabled)
- `assignRole()`, `removeRole()`, `syncRoles()`, `givePermissionTo()`, `revokePermissionTo()`, `syncPermissions()`
- `hasRole()`, `hasAnyRole()`, `hasAllRoles()`, `hasPermissionTo()`, `hasAnyPermission()`, `hasAllPermissions()`, `hasDirectPermission()`
- `isSuperAdmin()`, `canBypassPermissions()`
- A `scopeRole()` query scope: `User::role('admin')->get()`
- Cleanup of the user's role and permission assignments when the user is deleted (on
  force-delete for soft-deleting models)

Checks use your default guard (`auth.defaults.guard`) unless you pass one. Assignment methods
accept names, integer IDs, models, or arrays and collections of these, and any other type is a
`TypeError`. `syncRoles()` and `syncPermissions()` resolve every name first and replace the
assignments in one transaction. Pivot rows are matched by your model's primary key
(`getKey()`), whatever its column is called. If you override one of these methods, keep its
signature compatible.

It does not add anything related to authentication — no password handling, no tokens, no 2FA,
no passkeys. Add those yourself via Fortify, Sanctum, `laravel/passkeys`, or whatever your
application needs; `HasKeystone` composes cleanly alongside any of them.

## Pointing Keystone at a Non-Default Model or Table

By default, Keystone resolves your User model via `config('auth.providers.users.model')`. If
you need to point it at a different class explicitly (for example, a custom guard's model),
set it in `config/keystone.php`. If neither is set, or the class doesn't implement
`KeystoneUser`, Keystone throws a `LogicException` that says what to fix:

```php
'user' => [
    'model' => \App\Models\Admin::class,
],
```

Console commands (`keystone:assign-role`, etc.) and the RBAC migrations use this value —
falling back to `auth.providers.users.model` — to resolve which class to query. No table-name
or primary-key-type configuration is needed: Keystone reads your model's own `getTable()` and
detects UUID and ULID primary keys automatically. Commands accept a user's primary key
(integer, UUID, or ULID) or email.

## UUID, ULID, or BigInt Primary Keys

Keystone's role/permission pivot tables (`model_has_roles`, `model_has_permissions`) store
`model_id` as a UUID (`HasUuids`), a ULID (`HasUlids`), or an unsigned big integer, detected
automatically from your User model at migration time. No configuration is required — just run
`php artisan migrate` after installing.

`tenant_id` (added in both tenancy modes, NULL in single-tenant mode) is always a UUID column, regardless of your User
model's own primary key type — see [Multi-Tenancy Architecture](multi-tenancy.md).

## Additional Resources

- [Keystone Configuration](../config/keystone.php)
- [HasKeystone Trait](../src/Traits/HasKeystone.php)
- [Laravel Authentication Docs](https://laravel.com/docs/authentication)
