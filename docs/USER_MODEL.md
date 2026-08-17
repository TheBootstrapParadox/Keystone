# User Model Configuration

Keystone does not own a User model. It adds roles, permissions, and tenant scoping to
whatever User model your application already authenticates with (Fortify, Breeze, or anything
else) via a single trait.

## Setup

Add `BSPDX\Keystone\Traits\HasKeystone` to your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use BSPDX\Keystone\Traits\HasKeystone;

class User extends Authenticatable
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

That's the entire integration. The trait adds:

- `roles()` / `permissions()` relationships (tenant-aware when `keystone.features.multi_tenant` is enabled)
- `assignRole()`, `removeRole()`, `syncRoles()`, `givePermissionTo()`, `revokePermissionTo()`, `syncPermissions()`
- `hasRole()`, `hasAnyRole()`, `hasAllRoles()`, `hasPermissionTo()`, `hasAnyPermission()`, `hasAllPermissions()`, `hasDirectPermission()`
- `isSuperAdmin()`, `canBypassPermissions()`
- A `scopeRole()` query scope: `User::role('admin')->get()`

It does not add anything related to authentication — no password handling, no tokens, no 2FA,
no passkeys. Add those yourself via Fortify, Sanctum, `laravel/passkeys`, or whatever your
application needs; `HasKeystone` composes cleanly alongside any of them.

## Pointing Keystone at a Non-Default Model or Table

By default, Keystone resolves your User model via `config('auth.providers.users.model')`. If
you need to point it at a different class explicitly (for example, a custom guard's model),
set it in `config/keystone.php`:

```php
'user' => [
    'model' => \App\Models\Admin::class,
],
```

Console commands (`keystone:assign-role`, etc.) and the RBAC migrations use this value —
falling back to `auth.providers.users.model` — to resolve which class to query. No table-name
or primary-key-type configuration is needed: Keystone reads your model's own `getTable()` and
detects UUID primary keys via `uniqueIds()` automatically.

## UUID or BigInt Primary Keys

Keystone's role/permission pivot tables (`model_has_roles`, `model_has_permissions`) store
`model_id` as either a UUID or an unsigned big integer, detected automatically from your User
model at migration time (via `uniqueIds()`). No configuration is required — just run
`php artisan migrate` after installing.

`tenant_id` (when multi-tenancy is enabled) is always a UUID column, regardless of your User
model's own primary key type — see [Multi-Tenancy Architecture](multi-tenancy.md).

## Additional Resources

- [Keystone Configuration](../config/keystone.php)
- [HasKeystone Trait](../src/Traits/HasKeystone.php)
- [Laravel Authentication Docs](https://laravel.com/docs/authentication)
